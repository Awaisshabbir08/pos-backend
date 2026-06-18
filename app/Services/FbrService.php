<?php

namespace App\Services;

use App\Models\FbrSubmission;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts orders to FBR's Pakistan POS integration API.
 *
 * Without real FBR credentials this runs in "sandbox" mode: instead of hitting
 * the real endpoint it generates a deterministic mock invoice number + QR.
 * That lets the rest of the flow (storage, receipt rendering, retry queue,
 * admin UI) be developed and tested end-to-end. As soon as the tenant fills in
 * a real `fbr_token` and a non-sandbox `fbr_endpoint`, the same code POSTs for
 * real — no other change needed.
 */
class FbrService
{
    private const DEFAULT_ENDPOINT = 'https://gw.fbr.gov.pk/dist/v1/di_data/v1/di/postinvoicedata_sb';

    /**
     * Submit a completed order to FBR. Always writes an FbrSubmission row;
     * sets the order's fbr_invoice_number + fbr_qr_data on success.
     */
    public function submitOrder(Order $order): FbrSubmission
    {
        $tenant = Tenant::find($order->tenant_id);
        if (!$tenant) {
            return $this->recordSubmission($order, 'failed', null, null, [], [], 'Tenant not found');
        }

        if (!$tenant->fbr_enabled) {
            // FBR isn't on for this tenant — don't even log a submission row
            return $this->recordSubmission($order, 'pending', null, null, [], [], 'FBR not enabled — submission skipped');
        }

        $payload = $this->buildPayload($order, $tenant);

        // No production token configured → run in sandbox mode and fake a response
        if (empty($tenant->fbr_token)) {
            $mock = $this->sandboxResponse($order);
            $this->stampOrder($order, $mock['invoice_number'], $mock['qr_data']);
            return $this->recordSubmission(
                $order, 'success',
                $mock['invoice_number'], $mock['qr_data'],
                $payload, $mock,
                'Sandbox mode — no fbr_token configured. Replace with a real token to enable live FBR posting.'
            );
        }

        try {
            $endpoint = $tenant->fbr_endpoint ?: self::DEFAULT_ENDPOINT;
            $response = Http::timeout(15)
                ->withToken($tenant->fbr_token)
                ->acceptJson()
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $invoiceNumber = $data['InvoiceNumber'] ?? $data['invoice_number'] ?? null;
                $qrData        = $data['QRCode']        ?? $data['qr_code']        ?? null;
                $this->stampOrder($order, $invoiceNumber, $qrData);
                return $this->recordSubmission($order, 'success', $invoiceNumber, $qrData, $payload, $data, null);
            }

            return $this->recordSubmission(
                $order, 'failed', null, null,
                $payload, $response->json() ?: ['raw' => $response->body()],
                'FBR returned HTTP ' . $response->status()
            );

        } catch (\Throwable $e) {
            Log::warning('FBR submission threw: ' . $e->getMessage(), ['order_id' => $order->id]);
            return $this->recordSubmission($order, 'failed', null, null, $payload, [], $e->getMessage());
        }
    }

    /** Retry a previously-failed submission. */
    public function retry(FbrSubmission $submission): FbrSubmission
    {
        $order = $submission->order;
        if (!$order) {
            $submission->update(['error_message' => 'Order no longer exists', 'retry_count' => $submission->retry_count + 1]);
            return $submission;
        }
        $new = $this->submitOrder($order);
        $new->retry_count = $submission->retry_count + 1;
        $new->save();
        return $new;
    }

    private function buildPayload(Order $order, Tenant $tenant): array
    {
        $order->loadMissing(['orderItems.product', 'branch']);
        return [
            'InvoiceType'     => $order->status === 'refunded' ? 'Refund Invoice' : 'Sale Invoice',
            'InvoiceDate'     => optional($order->created_at)->toDateString(),
            'SellerNTNCNIC'   => $tenant->fbr_ntn,
            'SellerBusinessName' => $tenant->name,
            'POSID'           => $tenant->fbr_pos_id,
            'InvoiceRefNo'    => $order->order_number,
            'Items'           => $order->orderItems->map(fn ($i) => [
                'ItemName'      => $i->product?->name ?? ("Item #{$i->product_id}"),
                'Quantity'      => $i->quantity,
                'UnitPrice'     => (float) $i->unit_price,
                'TotalValue'    => (float) $i->subtotal,
                'SalesTax'      => 0,
            ])->all(),
            'TotalSalesTax'   => (float) ($order->tax_amount ?? 0),
            'TotalAmount'     => (float) $order->total_amount,
            'PaymentMode'     => $order->payment_method,
        ];
    }

    private function sandboxResponse(Order $order): array
    {
        $invoiceNumber = 'FBR-SANDBOX-' . now()->format('YmdHis') . '-' . $order->id;
        $qrData = 'https://pos.fbr.gov.pk/verify?inv=' . urlencode($invoiceNumber);
        return [
            'invoice_number' => $invoiceNumber,
            'qr_data'        => $qrData,
            'mock'           => true,
        ];
    }

    private function stampOrder(Order $order, ?string $invoiceNumber, ?string $qrData): void
    {
        if ($invoiceNumber || $qrData) {
            $order->forceFill([
                'fbr_invoice_number' => $invoiceNumber,
                'fbr_qr_data'        => $qrData,
            ])->save();
        }
    }

    private function recordSubmission(
        Order $order, string $status, ?string $invoice, ?string $qr,
        array $request, array $response, ?string $error
    ): FbrSubmission {
        return FbrSubmission::create([
            'tenant_id'        => $order->tenant_id,
            'order_id'         => $order->id,
            'invoice_number'   => $invoice,
            'qr_data'          => $qr,
            'status'           => $status,
            'request_payload'  => $request,
            'response_payload' => $response,
            'error_message'    => $error,
            'submitted_at'     => $status === 'pending' ? null : now(),
        ]);
    }
}
