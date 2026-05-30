<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        // Branch scoping: locked to user's branch, or accept ?branch_id=
        $branchId = $request->user()?->branch_id ?? $request->input('branch_id');
        $branchId = $branchId !== '' && $branchId !== null ? (int) $branchId : null;

        $branchOrders = fn () => Order::query()->when($branchId, fn($q) => $q->where('branch_id', $branchId));

        // Today's sales (completed orders)
        $todaySales = $branchOrders()
            ->whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('total_amount');

        // Today's orders count
        $todayOrdersCount = $branchOrders()
            ->whereDate('created_at', $today)
            ->count();

        // Total active products (global — products aren't branch-scoped)
        $totalProducts = Product::where('status', 'active')->count();

        // Distinct customers who have ordered from this branch
        // (when no branch filter, show the global customers count)
        if ($branchId) {
            $totalCustomers = $branchOrders()
                ->whereNotNull('customer_id')
                ->distinct('customer_id')
                ->count('customer_id');
        } else {
            $totalCustomers = Customer::count();
        }

        // Total revenue from all completed orders
        $totalRevenue = $branchOrders()
            ->where('status', 'completed')
            ->sum('total_amount');

        // Sales: last 7 days
        $monthlySales = collect(range(6, 0))->map(function (int $daysAgo) use ($branchOrders) {
            $date = now()->subDays($daysAgo)->toDateString();
            $sales = $branchOrders()
                ->whereDate('created_at', $date)
                ->where('status', 'completed')
                ->sum('total_amount');
            return [
                'date'  => $date,
                'sales' => (float) $sales,
            ];
        })->values();

        // Recent orders: last 10 for the branch
        $recentOrders = $branchOrders()
            ->with(['customer', 'branch'])
            ->withCount('orderItems')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (Order $order) {
                return [
                    'id'             => $order->id,
                    'order_number'   => $order->order_number,
                    'customer_name'  => $order->customer?->name ?? 'Walk-in Customer',
                    'branch_name'    => $order->branch?->name,
                    'total_amount'   => (float) $order->total_amount,
                    'items_count'    => $order->order_items_count,
                    'payment_method' => $order->payment_method,
                    'status'         => $order->status,
                    'created_at'     => $order->created_at,
                ];
            });

        // Low stock products (global, products aren't branch-scoped)
        $lowStockProducts = Product::where('status', 'active')
            ->where('stock_quantity', '<', 10)
            ->orderBy('stock_quantity')
            ->get(['id', 'name', 'sku', 'stock_quantity']);

        // Top 5 products by order frequency (filtered to this branch's orders if branch_id given)
        $topProductsQuery = OrderItem::select('order_items.product_id', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('order_items.product_id')
            ->orderByDesc('total_sold')
            ->with('product:id,name,sku,price')
            ->limit(5);

        if ($branchId) {
            $topProductsQuery->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.branch_id', $branchId);
        }

        $topProducts = $topProductsQuery->get()->map(function ($item) {
            return [
                'product_id'   => $item->product_id,
                'product_name' => $item->product?->name,
                'sku'          => $item->product?->sku,
                'price'        => $item->product?->price,
                'total_sold'   => (int) $item->total_sold,
            ];
        });

        // Per-branch breakdown (only when not scoped to a single branch).
        $perBranch = [];
        if ($branchId === null) {
            $perBranch = Branch::orderBy('name')->get()->map(function (Branch $branch) use ($today) {
                $base = fn () => Order::where('branch_id', $branch->id);

                return [
                    'branch_id'          => $branch->id,
                    'name'               => $branch->name,
                    'city'               => $branch->city,
                    'today_sales'        => (float) $base()
                        ->whereDate('created_at', $today)
                        ->where('status', 'completed')
                        ->sum('total_amount'),
                    'today_orders_count' => $base()
                        ->whereDate('created_at', $today)
                        ->count(),
                    'total_revenue'      => (float) $base()
                        ->where('status', 'completed')
                        ->sum('total_amount'),
                    'total_orders'       => $base()->count(),
                    'customers_count'    => $base()
                        ->whereNotNull('customer_id')
                        ->distinct('customer_id')
                        ->count('customer_id'),
                ];
            })->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Dashboard data retrieved successfully',
            'data'    => [
                'branch_id'           => $branchId,
                'today_sales'         => (float) $todaySales,
                'today_orders_count'  => $todayOrdersCount,
                'total_products'      => $totalProducts,
                'total_customers'     => $totalCustomers,
                'total_revenue'       => (float) $totalRevenue,
                'monthly_sales'       => $monthlySales,
                'recent_orders'       => $recentOrders,
                'low_stock_products'  => $lowStockProducts,
                'top_products'        => $topProducts,
                'per_branch'          => $perBranch,
            ],
        ]);
    }
}
