<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function sales(Request $request): JsonResponse
    {
        $from = $request->input('date_from') ?: now()->subDays(30)->toDateString();
        $to   = $request->input('date_to')   ?: now()->toDateString();

        $orders = $this->baseQuery($request)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);

        $completed = (clone $orders)->where('status', 'completed');

        // Daily series
        $byDay = (clone $completed)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as total, SUM(tax_amount) as tax')
            ->groupBy('date')->orderBy('date')->get()
            ->map(fn($r) => [
                'date'   => $r->date,
                'orders' => (int) $r->orders,
                'total'  => (float) $r->total,
                'tax'    => (float) $r->tax,
            ]);

        // By payment method
        $byPayment = (clone $completed)
            ->selectRaw('payment_method, COUNT(*) as orders, SUM(total_amount) as total')
            ->groupBy('payment_method')->get();

        // By service type
        $byServiceType = (clone $completed)
            ->selectRaw('service_type, COUNT(*) as orders, SUM(total_amount) as total')
            ->groupBy('service_type')->get();

        // Top products in this range
        $itemsQ = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('orders.status', 'completed');

        $userBranch = $request->user()?->branch_id;
        if ($userBranch) $itemsQ->where('orders.branch_id', $userBranch);
        elseif ($request->filled('branch_id')) $itemsQ->where('orders.branch_id', $request->branch_id);

        // Top products + COGS / margin per product. We use the snapshotted
        // unit_cost_at_sale when available; for legacy orders predating cost
        // tracking we fall back to the product's current cost_price.
        $topProducts = (clone $itemsQ)
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->selectRaw('order_items.product_id,
                         SUM(order_items.quantity) as qty,
                         SUM(order_items.subtotal) as revenue,
                         SUM(order_items.quantity * COALESCE(order_items.unit_cost_at_sale, products.cost_price, 0)) as cogs')
            ->groupBy('order_items.product_id')
            ->orderByDesc('qty')
            ->limit(10)
            ->with('product:id,name,sku')
            ->get()
            ->map(function ($r) {
                $revenue = (float) $r->revenue;
                $cogs    = (float) $r->cogs;
                $profit  = $revenue - $cogs;
                return [
                    'product_id'    => $r->product_id,
                    'name'          => $r->product?->name,
                    'sku'           => $r->product?->sku,
                    'qty_sold'      => (int) $r->qty,
                    'revenue'       => $revenue,
                    'cogs'          => $cogs,
                    'gross_profit'  => $profit,
                    'margin_pct'    => $revenue > 0 ? round($profit / $revenue * 100, 2) : null,
                ];
            });

        // Totals: revenue / COGS / gross profit across the period
        $totalsRow = (clone $itemsQ)
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->selectRaw('SUM(order_items.subtotal) as revenue,
                         SUM(order_items.quantity * COALESCE(order_items.unit_cost_at_sale, products.cost_price, 0)) as cogs')
            ->first();
        $totalRevenue = (float) ($totalsRow->revenue ?? 0);
        $totalCogs    = (float) ($totalsRow->cogs ?? 0);
        $grossProfit  = $totalRevenue - $totalCogs;

        // By waiter (when applicable)
        $byWaiter = (clone $completed)
            ->whereNotNull('waiter_id')
            ->selectRaw('waiter_id, COUNT(*) as orders, SUM(total_amount) as total')
            ->groupBy('waiter_id')
            ->with('waiter:id,name')
            ->get()
            ->map(fn($r) => [
                'waiter_id' => $r->waiter_id,
                'name'      => $r->waiter?->name ?? '—',
                'orders'    => (int) $r->orders,
                'total'     => (float) $r->total,
            ]);

        // By counter (POS station)
        $byCounter = (clone $completed)
            ->selectRaw('counter_id, COUNT(*) as orders, SUM(total_amount) as total')
            ->groupBy('counter_id')
            ->with('counter:id,name')
            ->get()
            ->map(fn($r) => [
                'counter_id' => $r->counter_id,
                'name'       => $r->counter?->name ?? 'Unassigned',
                'orders'     => (int) $r->orders,
                'total'      => (float) $r->total,
            ]);

        // By cashier (user who created the sale)
        $byCashier = (clone $completed)
            ->selectRaw('created_by_user_id, COUNT(*) as orders, SUM(total_amount) as total')
            ->groupBy('created_by_user_id')
            ->with('createdBy:id,name')
            ->get()
            ->map(fn($r) => [
                'user_id' => $r->created_by_user_id,
                'name'    => $r->createdBy?->name ?? '—',
                'orders'  => (int) $r->orders,
                'total'   => (float) $r->total,
            ]);

        // Cash vs Credit — credit = any non-cash tender (card/wallet/bank/other)
        $cashRow   = (clone $completed)->where('payment_method', 'cash')
            ->selectRaw('COUNT(*) as orders, SUM(total_amount) as total')->first();
        $creditRow = (clone $completed)->where('payment_method', '!=', 'cash')
            ->selectRaw('COUNT(*) as orders, SUM(total_amount) as total')->first();
        $cashVsCredit = [
            'cash'   => ['orders' => (int) ($cashRow->orders ?? 0),   'total' => (float) ($cashRow->total ?? 0)],
            'credit' => ['orders' => (int) ($creditRow->orders ?? 0), 'total' => (float) ($creditRow->total ?? 0)],
        ];

        // By category (from sold line items)
        $byCategory = (clone $itemsQ)
            ->leftJoin('products as p2', 'p2.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'p2.category_id')
            ->selectRaw('categories.name as category, SUM(order_items.quantity) as qty, SUM(order_items.subtotal) as revenue')
            ->groupBy('categories.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn($r) => [
                'category' => $r->category ?? 'Uncategorized',
                'qty_sold' => (int) $r->qty,
                'revenue'  => (float) $r->revenue,
            ]);

        // Full item-wise breakdown (all items, not just top 10)
        $byItem = (clone $itemsQ)
            ->selectRaw('order_items.product_id,
                         SUM(order_items.quantity) as qty,
                         SUM(order_items.subtotal) as revenue,
                         SUM(order_items.discount_amount) as discount')
            ->groupBy('order_items.product_id')
            ->orderByDesc('revenue')
            ->with('product:id,name,sku')
            ->get()
            ->map(fn($r) => [
                'product_id' => $r->product_id,
                'name'       => $r->product?->name,
                'sku'        => $r->product?->sku,
                'qty_sold'   => (int) $r->qty,
                'discount'   => (float) $r->discount,
                'revenue'    => (float) $r->revenue,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Sales report',
            'data'    => [
                'date_from'        => $from,
                'date_to'          => $to,
                'orders_count'     => (clone $completed)->count(),
                'gross_sales'      => (float) (clone $completed)->sum('total_amount'),
                'item_revenue'     => $totalRevenue,
                'total_cogs'       => $totalCogs,
                'gross_profit'     => $grossProfit,
                'margin_pct'       => $totalRevenue > 0 ? round($grossProfit / $totalRevenue * 100, 2) : null,
                'total_tax'        => (float) (clone $completed)->sum('tax_amount'),
                'total_service'    => (float) (clone $completed)->sum('service_charge_amount'),
                'total_discount'   => (float) (clone $completed)->sum('discount_amount'),
                'voided_count'     => (clone $orders)->where('status', 'voided')->count(),
                'refunded_count'   => (clone $orders)->where('status', 'refunded')->count(),
                'refunded_amount'  => (float) (clone $orders)->where('status', 'refunded')->sum('refunded_amount'),
                'by_day'           => $byDay,
                'by_payment'       => $byPayment,
                'by_service_type'  => $byServiceType,
                'by_waiter'        => $byWaiter,
                'by_counter'       => $byCounter,
                'by_cashier'       => $byCashier,
                'by_category'      => $byCategory,
                'by_item'          => $byItem,
                'cash_vs_credit'   => $cashVsCredit,
                'top_products'     => $topProducts,
            ],
        ]);
    }

    /**
     * Cash report — opening / closing cash per shift (cash register) plus a
     * summary of expected vs actual and the period's cash sales.
     */
    public function cash(Request $request): JsonResponse
    {
        $from = $request->input('date_from') ?: now()->subDays(30)->toDateString();
        $to   = $request->input('date_to')   ?: now()->toDateString();

        $q = \App\Models\CashRegister::query()
            ->with(['branch:id,name', 'counter:id,name', 'openedBy:id,name', 'closedBy:id,name'])
            ->whereBetween('opened_at', [$from . ' 00:00:00', $to . ' 23:59:59']);

        $userBranch = $request->user()?->branch_id;
        if ($userBranch) $q->where('branch_id', $userBranch);
        elseif ($request->filled('branch_id')) $q->where('branch_id', $request->branch_id);
        if ($request->filled('counter_id')) $q->where('counter_id', $request->counter_id);

        $registers = (clone $q)->orderByDesc('opened_at')->get()->map(fn($r) => [
            'id'              => $r->id,
            'branch'          => $r->branch?->name,
            'counter'         => $r->counter?->name ?? '—',
            'opened_by'       => $r->openedBy?->name,
            'closed_by'       => $r->closedBy?->name,
            'opened_at'       => $r->opened_at?->toDateTimeString(),
            'closed_at'       => $r->closed_at?->toDateTimeString(),
            'opening_cash'    => (float) $r->opening_cash,
            'expected_cash'   => $r->expected_cash !== null ? (float) $r->expected_cash : null,
            'actual_cash'     => $r->actual_cash !== null ? (float) $r->actual_cash : null,
            'cash_difference' => $r->cash_difference !== null ? (float) $r->cash_difference : null,
            'status'          => $r->status,
        ]);

        // Cash sales in the period (orders settled with cash)
        $cashSales = (float) $this->baseQuery($request)
            ->where('status', 'completed')
            ->where('payment_method', 'cash')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->sum('total_amount');

        return response()->json([
            'success' => true,
            'message' => 'Cash report',
            'data'    => [
                'date_from'      => $from,
                'date_to'        => $to,
                'registers'      => $registers,
                'summary'        => [
                    'shifts'            => $registers->count(),
                    'open_shifts'       => $registers->where('status', 'open')->count(),
                    'total_opening'     => round($registers->sum('opening_cash'), 2),
                    'total_expected'    => round($registers->sum(fn($r) => $r['expected_cash'] ?? 0), 2),
                    'total_actual'      => round($registers->sum(fn($r) => $r['actual_cash'] ?? 0), 2),
                    'total_difference'  => round($registers->sum(fn($r) => $r['cash_difference'] ?? 0), 2),
                    'cash_sales'        => round($cashSales, 2),
                ],
            ],
        ]);
    }

    /** Download the sales report as CSV. */
    public function exportSales(Request $request): StreamedResponse
    {
        $from = $request->input('date_from') ?: now()->subDays(30)->toDateString();
        $to   = $request->input('date_to')   ?: now()->toDateString();

        $orders = $this->baseQuery($request)
            ->with(['branch', 'customer', 'waiter'])
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('status', 'completed')
            ->orderByDesc('created_at');

        $filename = "sales-{$from}-to-{$to}.csv";

        return response()->streamDownload(function () use ($orders) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['Order #', 'Date', 'Branch', 'Customer', 'Waiter', 'Service Type',
                         'Subtotal', 'Tax', 'Service Charge', 'Discount', 'Total',
                         'Payment Method', 'Status']);

            $orders->chunk(200, function ($rows) use ($h) {
                foreach ($rows as $o) {
                    $subtotal = (float)$o->total_amount - (float)$o->tax_amount
                              - (float)$o->service_charge_amount + (float)$o->discount_amount;
                    fputcsv($h, [
                        $o->order_number,
                        $o->created_at?->toDateTimeString(),
                        $o->branch?->name,
                        $o->customer?->name ?? 'Walk-in',
                        $o->waiter?->name,
                        $o->service_type,
                        number_format($subtotal, 2, '.', ''),
                        $o->tax_amount,
                        $o->service_charge_amount,
                        $o->discount_amount,
                        $o->total_amount,
                        $o->payment_method,
                        $o->status,
                    ]);
                }
            });

            fclose($h);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function baseQuery(Request $request)
    {
        $q = Order::query();
        $userBranch = $request->user()?->branch_id;
        if ($userBranch) {
            $q->where('branch_id', $userBranch);
        } elseif ($request->filled('branch_id')) {
            $q->where('branch_id', $request->branch_id);
        }
        return $q;
    }
}
