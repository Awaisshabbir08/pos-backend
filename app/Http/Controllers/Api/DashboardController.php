<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Platform mode: super-admin with no specific tenant picked.
        // Show tenant-management oriented metrics, not store-level numbers.
        if (TenantContext::isSuperAdmin() && TenantContext::id() === null) {
            return $this->platformDashboard();
        }

        return $this->tenantDashboard($request);
    }

    /**
     * Platform-level overview for the super-admin owner.
     * Counts tenants, subscriptions at risk, and a quick per-tenant grid.
     */
    private function platformDashboard(): JsonResponse
    {
        $today    = Carbon::now()->toDateString();
        $in30Days = Carbon::now()->addDays(30)->toDateString();

        $tenantsTotal    = Tenant::count();
        $tenantsActive   = Tenant::where('status', 'active')->count();
        $tenantsTrial    = Tenant::where('status', 'trial')->count();
        $tenantsInactive = Tenant::where('status', 'inactive')->count();

        // Subscriptions expiring in the next 30 days (still active today)
        $expiringSoon = Tenant::whereIn('status', ['active', 'trial'])
            ->whereNotNull('subscription_expires_at')
            ->whereBetween('subscription_expires_at', [$today, $in30Days])
            ->orderBy('subscription_expires_at')
            ->limit(10)
            ->get(['id', 'name', 'slug', 'plan', 'status', 'subscription_expires_at']);

        // Already expired but not yet flipped to inactive
        $expired = Tenant::whereIn('status', ['active', 'trial'])
            ->whereNotNull('subscription_expires_at')
            ->where('subscription_expires_at', '<', $today)
            ->orderBy('subscription_expires_at')
            ->limit(10)
            ->get(['id', 'name', 'slug', 'plan', 'status', 'subscription_expires_at']);

        // Recently created tenants
        $recentTenants = Tenant::orderByDesc('created_at')->limit(5)
            ->get(['id', 'name', 'slug', 'plan', 'status', 'created_at']);

        // Platform totals (using allTenants() to bypass scope safely)
        $branchesTotal   = Branch::allTenants()->count();
        $usersTotal      = User::allTenants()->whereNotNull('tenant_id')->count();
        $ordersTotal     = Order::allTenants()->count();
        $platformRevenue = (float) Order::allTenants()
            ->where('status', 'completed')->sum('total_amount');
        $todayOrders     = Order::allTenants()->whereDate('created_at', $today)->count();
        $todayRevenue    = (float) Order::allTenants()
            ->whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('total_amount');

        // Per-tenant overview (top 10 by recent activity)
        $perTenant = Tenant::orderBy('name')->get()->map(function (Tenant $t) use ($today) {
            $orders = Order::allTenants()->where('tenant_id', $t->id);
            return [
                'id'                       => $t->id,
                'name'                     => $t->name,
                'slug'                     => $t->slug,
                'plan'                     => $t->plan,
                'status'                   => $t->status,
                'subscription_expires_at'  => $t->subscription_expires_at?->toDateString(),
                'branches_count'           => Branch::allTenants()->where('tenant_id', $t->id)->count(),
                'users_count'              => User::allTenants()->where('tenant_id', $t->id)->count(),
                'today_sales'              => (float) (clone $orders)
                    ->whereDate('created_at', $today)->where('status', 'completed')->sum('total_amount'),
                'today_orders_count'       => (clone $orders)->whereDate('created_at', $today)->count(),
                'total_revenue'            => (float) (clone $orders)->where('status', 'completed')->sum('total_amount'),
                'total_orders'             => (clone $orders)->count(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Platform dashboard retrieved successfully',
            'data'    => [
                'mode'              => 'platform',
                'tenants_total'     => $tenantsTotal,
                'tenants_active'    => $tenantsActive,
                'tenants_trial'     => $tenantsTrial,
                'tenants_inactive'  => $tenantsInactive,
                'branches_total'    => $branchesTotal,
                'users_total'       => $usersTotal,
                'orders_total'      => $ordersTotal,
                'platform_revenue'  => $platformRevenue,
                'today_orders'      => $todayOrders,
                'today_revenue'     => $todayRevenue,
                'expiring_soon'     => $expiringSoon,
                'expired'           => $expired,
                'recent_tenants'    => $recentTenants,
                'per_tenant'        => $perTenant,
            ],
        ]);
    }

    /**
     * Store-level dashboard for tenant admins / cashiers, and for the
     * super-admin "view as <tenant>" mode.
     */
    private function tenantDashboard(Request $request): JsonResponse
    {
        $today = Carbon::now()->toDateString();

        $branchId = $request->user()?->branch_id ?? $request->input('branch_id');
        $branchId = $branchId !== '' && $branchId !== null ? (int) $branchId : null;

        $branchOrders = fn () => Order::query()->when($branchId, fn($q) => $q->where('branch_id', $branchId));

        $todaySales = $branchOrders()
            ->whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('total_amount');

        $todayOrdersCount = $branchOrders()->whereDate('created_at', $today)->count();

        $totalProducts = Product::where('status', 'active')->count();

        if ($branchId) {
            $totalCustomers = $branchOrders()
                ->whereNotNull('customer_id')
                ->distinct('customer_id')
                ->count('customer_id');
        } else {
            $totalCustomers = Customer::count();
        }

        $totalRevenue = $branchOrders()->where('status', 'completed')->sum('total_amount');

        $monthlySales = collect(range(6, 0))->map(function (int $daysAgo) use ($branchOrders) {
            $date = now()->subDays($daysAgo)->toDateString();
            $sales = $branchOrders()->whereDate('created_at', $date)
                ->where('status', 'completed')->sum('total_amount');
            return ['date' => $date, 'sales' => (float) $sales];
        })->values();

        $recentOrders = $branchOrders()
            ->with(['customer', 'branch'])
            ->withCount('orderItems')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Order $o) => [
                'id'             => $o->id,
                'order_number'   => $o->order_number,
                'customer_name'  => $o->customer?->name ?? 'Walk-in Customer',
                'branch_name'    => $o->branch?->name,
                'total_amount'   => (float) $o->total_amount,
                'items_count'    => $o->order_items_count,
                'payment_method' => $o->payment_method,
                'status'         => $o->status,
                'created_at'     => $o->created_at,
            ]);

        $lowStockProducts = Product::where('status', 'active')
            ->where('stock_quantity', '<', 10)
            ->orderBy('stock_quantity')
            ->get(['id', 'name', 'sku', 'stock_quantity']);

        $topProductsQuery = OrderItem::select('order_items.product_id', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('order_items.product_id')
            ->orderByDesc('total_sold')
            ->with('product:id,name,sku,price')
            ->limit(5);

        if ($branchId) {
            $topProductsQuery->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.branch_id', $branchId);
        }

        $topProducts = $topProductsQuery->get()->map(fn ($i) => [
            'product_id'   => $i->product_id,
            'product_name' => $i->product?->name,
            'sku'          => $i->product?->sku,
            'price'        => $i->product?->price,
            'total_sold'   => (int) $i->total_sold,
        ]);

        $perBranch = [];
        if ($branchId === null) {
            $perBranch = Branch::orderBy('name')->get()->map(function (Branch $branch) use ($today) {
                $base = fn () => Order::where('branch_id', $branch->id);
                return [
                    'branch_id'          => $branch->id,
                    'name'               => $branch->name,
                    'city'               => $branch->city,
                    'today_sales'        => (float) $base()->whereDate('created_at', $today)->where('status', 'completed')->sum('total_amount'),
                    'today_orders_count' => $base()->whereDate('created_at', $today)->count(),
                    'total_revenue'      => (float) $base()->where('status', 'completed')->sum('total_amount'),
                    'total_orders'       => $base()->count(),
                    'customers_count'    => $base()->whereNotNull('customer_id')->distinct('customer_id')->count('customer_id'),
                ];
            })->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Dashboard data retrieved successfully',
            'data'    => [
                'mode'                => 'tenant',
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
