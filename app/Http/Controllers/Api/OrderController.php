<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Requests\Api\UpdateOrderRequest;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\FbrService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['branch', 'customer', 'waiter', 'table', 'rider', 'orderItems.product']);

        $userBranch = $request->user()?->branch_id;
        if ($userBranch) {
            $query->where('branch_id', $userBranch);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        foreach (['status', 'customer_id', 'payment_method', 'service_type'] as $col) {
            if ($request->filled($col)) $query->where($col, $request->input($col));
        }
        if ($request->filled('date'))      $query->whereDate('created_at', $request->date);
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->filled('search'))    $query->where('order_number', 'like', '%' . $request->search . '%');

        $perPage = $request->get('per_page', 15);
        $orders = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'data'    => $orders,
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        // hold=true means "park this order, no payment yet"
        $hold = $request->boolean('hold');

        DB::beginTransaction();

        try {
            $items = $request->input('items');
            $branchId = $request->user()?->branch_id ?? $request->input('branch_id');
            $totalAmount = 0;
            $orderItemsData = [];

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $available = $product->stockFor($branchId);

                if ($available < $item['quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for product: {$product->name}. Available: {$available}",
                        'data'    => null,
                    ], 422);
                }

                // Resolve picked modifiers (if any) and add their price delta to the line price.
                $pickedModifiers = [];
                $modifierTotal = 0.0;
                if (!empty($item['modifiers']) && is_array($item['modifiers'])) {
                    $modifierIds = array_filter(array_map('intval', $item['modifiers']));
                    if ($modifierIds) {
                        $modifiers = Modifier::whereIn('id', $modifierIds)->get();
                        foreach ($modifiers as $m) {
                            $pickedModifiers[] = $m;
                            $modifierTotal += (float) $m->price_delta;
                        }
                    }
                }

                $unitPriceWithMods = (float) $product->price + $modifierTotal;
                $subtotal = $unitPriceWithMods * $item['quantity'];
                $totalAmount += $subtotal;

                $orderItemsData[] = [
                    'product_id'         => $product->id,
                    'quantity'           => $item['quantity'],
                    'unit_price'         => $unitPriceWithMods,
                    // Snapshot the product's CURRENT cost at sale time, so historical
                    // COGS / margin reports stay stable when cost changes later.
                    'unit_cost_at_sale'  => (float) $product->cost_price,
                    'subtotal'           => $subtotal,
                    '_modifiers'         => $pickedModifiers, // stripped before insert
                ];
            }

            $taxAmount           = (float) $request->input('tax_amount', 0);
            $serviceChargeAmount = (float) $request->input('service_charge_amount', 0);
            $discountAmount      = (float) $request->input('discount_amount', 0);

            // --- Coupon ---------------------------------------------------------
            $coupon = null;
            if ($code = $request->input('coupon_code')) {
                $coupon = Coupon::where('code', strtoupper($code))->first();
                if ($coupon) {
                    if ($reason = $coupon->reasonInvalidFor($totalAmount)) {
                        DB::rollBack();
                        return response()->json(['success'=>false,'message'=>$reason,'data'=>null], 422);
                    }
                    $discountAmount += $coupon->computeDiscount($totalAmount);
                }
            }

            // --- Delivery zone fee ---------------------------------------------
            $serviceType = $request->input('service_type', 'dine_in');
            $deliveryFee = 0.0;
            $deliveryZoneId = null;
            if ($serviceType === 'delivery' && $request->filled('delivery_zone_id')) {
                $zone = DeliveryZone::find($request->input('delivery_zone_id'));
                if ($zone) {
                    $deliveryFee = (float) $zone->fee;
                    $deliveryZoneId = $zone->id;
                }
            }

            // Tips / gratuity
            $tipAmount    = (float) $request->input('tip_amount', 0);
            $tipWaiterId  = $request->input('tip_waiter_id');

            $preDiscount    = $totalAmount + $taxAmount + $serviceChargeAmount + $deliveryFee;
            $discountAmount = min($discountAmount, $preDiscount);

            $finalTotal   = max(0, $preDiscount - $discountAmount + $tipAmount);

            // Split payment — if a `payments` array is sent, sum its amounts;
            // otherwise fall back to the single-tender flow with `paid_amount`.
            $paymentsPayload = $request->input('payments');
            if (is_array($paymentsPayload) && count($paymentsPayload) > 0) {
                $paidAmount = array_sum(array_map(fn($p) => (float) ($p['amount'] ?? 0), $paymentsPayload));
            } else {
                $paidAmount = (float) $request->input('paid_amount', 0);
            }
            $changeAmount = $hold ? 0 : max(0, $paidAmount - $finalTotal);

            $order = Order::create([
                'branch_id'             => $branchId,
                'customer_id'           => $request->input('customer_id'),
                'waiter_id'             => $serviceType !== 'delivery' ? $request->input('waiter_id') : null,
                'table_id'              => $serviceType === 'dine_in' ? $request->input('table_id') : null,
                'rider_id'              => $serviceType === 'delivery' ? $request->input('rider_id') : null,
                'service_type'          => $serviceType,
                'order_number'          => $this->generateOrderNumber(),
                'total_amount'          => $finalTotal,
                'tax_amount'            => $taxAmount,
                'service_charge_amount' => $serviceChargeAmount,
                'discount_amount'       => $discountAmount,
                'delivery_fee'          => $deliveryFee,
                'delivery_zone_id'      => $deliveryZoneId,
                'coupon_id'             => $coupon?->id,
                'tip_amount'            => $tipAmount,
                'tip_waiter_id'         => $tipWaiterId,
                'paid_amount'           => $hold ? 0 : $paidAmount,
                'change_amount'         => $changeAmount,
                // payment_method is NOT NULL; default to 'cash' on hold (real method set on resume).
                // When split-payment is used, this stores the FIRST tender for display purposes.
                'payment_method'        => $request->input('payment_method')
                    ?: (is_array($paymentsPayload) && !empty($paymentsPayload) ? ($paymentsPayload[0]['method'] ?? 'cash') : 'cash'),
                'status'                => $hold ? 'held' : 'completed',
                'held_at'               => $hold ? now() : null,
                'notes'                 => $request->input('notes'),
            ]);

            // Persist line items, then their modifier snapshots
            foreach ($orderItemsData as $itemData) {
                $modifiers = $itemData['_modifiers'] ?? [];
                unset($itemData['_modifiers']);
                $itemData['order_id'] = $order->id;
                $orderItem = $order->orderItems()->create($itemData);
                foreach ($modifiers as $m) {
                    $orderItem->modifiers()->create([
                        'modifier_id' => $m->id,
                        'name'        => $m->name,
                        'price_delta' => $m->price_delta,
                    ]);
                }
            }

            // Persist split-payment rows (one per tender). For single-tender
            // orders we still record one row so reports always have a uniform
            // payments table to read from.
            if (!$hold) {
                if (is_array($paymentsPayload) && count($paymentsPayload) > 0) {
                    foreach ($paymentsPayload as $p) {
                        $order->payments()->create([
                            'method'    => $p['method'],
                            'amount'    => (float) $p['amount'],
                            'reference' => $p['reference'] ?? null,
                        ]);
                    }
                } else {
                    $order->payments()->create([
                        'method' => $order->payment_method,
                        'amount' => $paidAmount,
                    ]);
                }
            }

            // Deduct stock only on completed (paid) orders. Held orders don't touch stock
            // until they're resumed and paid — common POS behaviour.
            if (!$hold) {
                $this->deductStock($items, $branchId);
                if ($coupon) $coupon->increment('used_count');
            }

            DB::commit();

            // After the order is committed, attempt to post it to FBR (Pakistan).
            // This runs OUTSIDE the DB transaction so an FBR failure can never
            // roll back a paid sale — the submission row is recorded and can
            // be retried from the FBR admin page.
            if (!$hold) {
                $tenant = Tenant::find($order->tenant_id);
                if ($tenant && $tenant->fbr_enabled) {
                    app(FbrService::class)->submitOrder($order);
                }
            }

            $order->load(['branch', 'customer', 'waiter', 'table', 'rider', 'coupon', 'deliveryZone', 'orderItems.product', 'orderItems.modifiers', 'payments', 'tipWaiter']);

            return response()->json([
                'success' => true,
                'message' => $hold ? 'Order held successfully' : 'Order created successfully',
                'data'    => $order,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage(),
                'data'    => null,
            ], 500);
        }
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['branch', 'customer', 'waiter', 'table', 'rider', 'coupon', 'deliveryZone', 'orderItems.product', 'orderItems.modifiers', 'payments', 'tipWaiter']);
        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully',
            'data'    => $order,
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $previousStatus = $order->status;
        $order->update($request->validated());

        // Restore stock if order is cancelled (legacy path; voids go through the dedicated endpoint)
        if ($request->input('status') === 'cancelled' && $previousStatus !== 'cancelled') {
            $this->restoreStock($order);
        }

        $order->load(['branch', 'customer', 'waiter', 'table', 'rider', 'orderItems.product']);
        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data'    => $order,
        ]);
    }

    public function destroy(\Illuminate\Http\Request $request, Order $order): JsonResponse
    {
        $force = $request->boolean('force');

        // Completed / voided / refunded orders are the financial audit trail.
        // Refuse normal delete; force=true lets admins wipe a mistake (test order,
        // duplicate, demo data). On force we also restock if the order had
        // active stock impact.
        if (!$force && in_array($order->status, ['completed', 'voided', 'refunded'])) {
            return response()->json([
                'success' => false,
                'message' => 'This is a completed/voided/refunded order. Add ?force=true to delete it anyway, or use void/refund to keep the audit trail.',
                'data'    => null,
            ], 422);
        }

        DB::transaction(function () use ($order, $force) {
            // If the order is being force-deleted while still active, restore stock so
            // we don't leak inventory.
            if ($force && $order->status === 'completed') {
                $this->restoreStock($order);
            }
            // Remove children explicitly so MySQL FK ordering can't cause a 500.
            \DB::table('order_item_modifiers')->whereIn(
                'order_item_id', \DB::table('order_items')->where('order_id', $order->id)->pluck('id')
            )->delete();
            $order->orderItems()->delete();
            \DB::table('order_payments')->where('order_id', $order->id)->delete();
            \DB::table('fbr_submissions')->where('order_id', $order->id)->delete();
            $order->delete();
        });

        Audit::log('order.delete', $order, ['force' => $force, 'status' => $order->status]);

        return response()->json([
            'success' => true,
            'message' => $force ? 'Order force-deleted.' : 'Order deleted.',
            'data'    => null,
        ]);
    }

    /**
     * Resume a held order — converts it to a completed sale.
     */
    public function resume(Request $request, Order $order): JsonResponse
    {
        if ($order->status !== 'held') {
            return response()->json([
                'success' => false,
                'message' => 'Only held orders can be resumed.',
                'data'    => null,
            ], 422);
        }

        $paidAmount = (float) $request->input('paid_amount', $order->total_amount);
        $change     = max(0, $paidAmount - (float) $order->total_amount);

        DB::beginTransaction();
        try {
            $order->update([
                'status'         => 'completed',
                'held_at'        => null,
                'paid_amount'    => $paidAmount,
                'change_amount'  => $change,
                'payment_method' => $request->input('payment_method', $order->payment_method),
            ]);

            // Now is the time to deduct stock (we skipped it on hold)
            $items = $order->orderItems->map(fn($i) => [
                'product_id' => $i->product_id, 'quantity' => $i->quantity
            ])->toArray();
            $this->deductStock($items, $order->branch_id);

            // Count the coupon usage now that the held order is actually paid
            if ($order->coupon_id && $order->coupon) {
                $order->coupon->increment('used_count');
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success'=>false, 'message'=>'Resume failed: '.$e->getMessage(), 'data'=>null], 500);
        }

        $order->load(['branch', 'customer', 'waiter', 'table', 'rider', 'orderItems.product']);
        return response()->json([
            'success' => true,
            'message' => 'Order resumed and completed',
            'data'    => $order,
        ]);
    }

    /**
     * Void a completed order — cancels the sale and restocks items.
     */
    public function void(Request $request, Order $order): JsonResponse
    {
        if (in_array($order->status, ['voided', 'refunded'])) {
            return response()->json(['success'=>false,'message'=>'Order is already voided/refunded.','data'=>null], 422);
        }

        DB::beginTransaction();
        try {
            // Restock if the order had deducted from stock
            if (in_array($order->status, ['completed'])) {
                $this->restoreStock($order);
            }

            $order->update([
                'status'      => 'voided',
                'voided_at'   => now(),
                'void_reason' => $request->input('reason') ?: 'Voided by user',
            ]);
            Audit::log('order.void', $order, ['reason' => $order->void_reason]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>'Void failed: '.$e->getMessage(),'data'=>null], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order voided and stock restored',
            'data'    => $order->fresh(),
        ]);
    }

    /**
     * Refund a completed order — restocks items and records refunded amount.
     */
    public function refund(Request $request, Order $order): JsonResponse
    {
        if ($order->status !== 'completed') {
            return response()->json(['success'=>false,'message'=>'Only completed orders can be refunded.','data'=>null], 422);
        }

        $amount = (float) $request->input('amount', $order->total_amount);
        $amount = min($amount, (float) $order->total_amount);

        DB::beginTransaction();
        try {
            $this->restoreStock($order);
            $order->update([
                'status'          => 'refunded',
                'refunded_at'     => now(),
                'refunded_amount' => $amount,
                'void_reason'     => $request->input('reason'),
            ]);
            Audit::log('order.refund', $order, ['amount' => $amount, 'reason' => $request->input('reason')]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>'Refund failed: '.$e->getMessage(),'data'=>null], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order refunded and stock restored',
            'data'    => $order->fresh(),
        ]);
    }

    /**
     * Receipt payload — formatted strings + tenant metadata for the print view.
     */
    public function receipt(Order $order): JsonResponse
    {
        $order->load(['branch', 'customer', 'waiter', 'table', 'rider', 'orderItems.product', 'orderItems.modifiers', 'coupon', 'deliveryZone', 'payments', 'tipWaiter', 'tenant']);

        $tenant = Tenant::find($order->tenant_id);

        return response()->json([
            'success' => true,
            'message' => 'Receipt data retrieved',
            'data'    => [
                'tenant' => $tenant ? [
                    'name'           => $tenant->name,
                    'contact_email'  => $tenant->contact_email,
                    'contact_phone'  => $tenant->contact_phone,
                    'currency'       => $tenant->currency ?? 'PKR',
                    'logo'           => $tenant->logo,
                    'receipt_header' => $tenant->receipt_header,
                    'receipt_footer' => $tenant->receipt_footer,
                ] : null,
                'order'  => $order,
            ],
        ]);
    }

    /* ---------- helpers ---------- */

    private function deductStock(array $items, ?int $branchId): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            if ($product) $product->decrementStockFor($branchId, (int) $item['quantity']);
        }
    }

    private function restoreStock(Order $order): void
    {
        $branchId = $order->branch_id;
        foreach ($order->orderItems as $item) {
            $product = Product::find($item->product_id);
            if ($product) $product->incrementStockFor($branchId, (int) $item->quantity);
        }
    }

    private function generateOrderNumber(): string
    {
        $prefix = 'ORD-' . now()->format('Ymd') . '-';
        $last = Order::where('order_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('order_number');

        $nextNum = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
}
