<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTenantRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Rider;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Waiter;
use App\Support\Audit;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class TenantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query()->withCount('users')->withCount('branches');

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%{$term}%")
                                     ->orWhere('slug', 'like', "%{$term}%"));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);

        return response()->json([
            'success' => true,
            'message' => 'Tenants retrieved successfully',
            'data'    => $query->orderBy('name')->paginate($perPage),
        ]);
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $data       = $request->validated();
        $wantsAdmin = (bool) ($data['create_admin'] ?? false);

        try {
            $result = DB::transaction(function () use ($data, $wantsAdmin) {
                if (empty($data['slug'])) {
                    $data['slug'] = $this->uniqueSlug($data['name']);
                }

                $adminFields = collect($data)->only(['create_admin', 'admin_name', 'admin_email', 'admin_password'])->toArray();
                foreach (array_keys($adminFields) as $k) unset($data[$k]);

                $tenant = Tenant::create($data);

                $admin = null;
                if ($wantsAdmin) {
                    // Pin the tenant context so BelongsToTenant stamps the new
                    // user with the just-created tenant id.
                    TenantContext::set($tenant->id, false);
                    try {
                        $admin = User::create([
                            'tenant_id' => $tenant->id,
                            'name'      => $adminFields['admin_name'],
                            'email'     => $adminFields['admin_email'],
                            'password'  => Hash::make($adminFields['admin_password']),
                            'status'    => 'active',
                        ]);
                        $admin->syncRoles(['admin']);
                    } finally {
                        TenantContext::set(null, true);
                    }
                }

                return ['tenant' => $tenant, 'admin' => $admin];
            });
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant: ' . $e->getMessage(),
                'data'    => null,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $wantsAdmin ? 'Tenant and initial admin created successfully' : 'Tenant created successfully',
            'data'    => [
                'tenant' => $result['tenant'],
                'admin'  => $result['admin'] ? [
                    'id'    => $result['admin']->id,
                    'name'  => $result['admin']->name,
                    'email' => $result['admin']->email,
                ] : null,
            ],
        ], 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->loadCount('users')->loadCount('branches');

        return response()->json([
            'success' => true,
            'message' => 'Tenant retrieved successfully',
            'data'    => $tenant,
        ]);
    }

    public function update(StoreTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $previousStatus = $tenant->status;

        $data = collect($request->validated())
            ->except(['create_admin', 'admin_name', 'admin_email', 'admin_password'])
            ->toArray();

        $tenant->update($data);

        if ($tenant->status === 'inactive' && $previousStatus !== 'inactive') {
            $this->revokeTenantTokens($tenant);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tenant updated successfully',
            'data'    => $tenant->fresh(),
        ]);
    }

    public function toggleStatus(Tenant $tenant): JsonResponse
    {
        $tenant->status = $tenant->status === 'inactive' ? 'active' : 'inactive';
        $tenant->save();

        if ($tenant->status === 'inactive') {
            $this->revokeTenantTokens($tenant);
            Audit::log('tenant.disable', $tenant);
        } else {
            Audit::log('tenant.enable', $tenant);
        }

        return response()->json([
            'success' => true,
            'message' => $tenant->status === 'active' ? 'Tenant enabled' : 'Tenant disabled and active sessions revoked',
            'data'    => $tenant,
        ]);
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->revokeTenantTokens($tenant);
        Audit::log('tenant.delete', $tenant, ['name' => $tenant->name]);

        try {
            $tenant->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant cannot be deleted because it still has linked records (orders, cash registers, etc.). Reset its demo data or remove dependent records first.',
                    'data'    => null,
                ], 422);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Tenant deleted successfully',
            'data'    => null,
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base ?: 'tenant';
        $i = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    /**
     * Demo reset — wipes all sales data for a tenant so super-admin can rerun a clean demo.
     * Keeps the tenant, branches, users, products, customers; deletes only orders + cash registers.
     */
    public function resetDemo(Tenant $tenant): JsonResponse
    {
        // Allow this only for the Default Store sandbox to prevent client-data accidents.
        if ($tenant->slug !== 'default-store') {
            return response()->json([
                'success' => false,
                'message' => 'Demo reset is only allowed on the Default Store tenant.',
                'data'    => null,
            ], 422);
        }

        OrderItem::allTenants()->whereIn('order_id',
            Order::allTenants()->where('tenant_id', $tenant->id)->pluck('id')
        )->delete();
        Order::allTenants()->where('tenant_id', $tenant->id)->delete();
        \App\Models\CashRegister::allTenants()->where('tenant_id', $tenant->id)->delete();

        Audit::log('tenant.demo_reset', $tenant);

        return response()->json([
            'success' => true,
            'message' => "Demo data reset for {$tenant->name}. All orders and cash registers cleared.",
            'data'    => null,
        ]);
    }

    private function revokeTenantTokens(Tenant $tenant): void
    {
        $userIds = User::allTenants()->where('tenant_id', $tenant->id)->pluck('id');
        if ($userIds->isEmpty()) return;

        PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $userIds)
            ->delete();
    }
}
