<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = now()->toDateString();

        // Today's sales (completed orders)
        $todaySales = Order::whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('total_amount');

        // Today's orders count
        $todayOrdersCount = Order::whereDate('created_at', $today)->count();

        // Total active products
        $totalProducts = Product::where('status', 'active')->count();

        // Total customers
        $totalCustomers = Customer::count();

        // Total revenue from all completed orders
        $totalRevenue = Order::where('status', 'completed')->sum('total_amount');

        // Monthly sales: last 7 days with date and sales amount
        $monthlySales = collect(range(6, 0))->map(function (int $daysAgo) {
            $date = now()->subDays($daysAgo)->toDateString();
            $sales = Order::whereDate('created_at', $date)
                ->where('status', 'completed')
                ->sum('total_amount');
            return [
                'date'  => $date,
                'sales' => (float) $sales,
            ];
        })->values();

        // Recent orders: last 10
        $recentOrders = Order::with('customer')
            ->withCount('orderItems')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (Order $order) {
                return [
                    'id'             => $order->id,
                    'order_number'   => $order->order_number,
                    'customer_name'  => $order->customer?->name ?? 'Walk-in Customer',
                    'total_amount'   => (float) $order->total_amount,
                    'items_count'    => $order->order_items_count,
                    'payment_method' => $order->payment_method,
                    'status'         => $order->status,
                    'created_at'     => $order->created_at,
                ];
            });

        // Low stock products (stock < 10)
        $lowStockProducts = Product::where('status', 'active')
            ->where('stock_quantity', '<', 10)
            ->orderBy('stock_quantity')
            ->get(['id', 'name', 'sku', 'stock_quantity']);

        // Top 5 products by order frequency
        $topProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->with('product:id,name,sku,price')
            ->get()
            ->map(function ($item) {
                return [
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product?->name,
                    'sku'          => $item->product?->sku,
                    'price'        => $item->product?->price,
                    'total_sold'   => (int) $item->total_sold,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Dashboard data retrieved successfully',
            'data'    => [
                'today_sales'         => (float) $todaySales,
                'today_orders_count'  => $todayOrdersCount,
                'total_products'      => $totalProducts,
                'total_customers'     => $totalCustomers,
                'total_revenue'       => (float) $totalRevenue,
                'monthly_sales'       => $monthlySales,
                'recent_orders'       => $recentOrders,
                'low_stock_products'  => $lowStockProducts,
                'top_products'        => $topProducts,
            ],
        ]);
    }
}
