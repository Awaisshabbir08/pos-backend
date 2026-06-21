<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Modules paired with the CRUD actions each supports.
     * Use this list whenever permissions are introduced or referenced.
     */
    public const MODULES = [
        'products'   => ['view', 'create', 'update', 'delete'],
        'categories' => ['view', 'create', 'update', 'delete'],
        'customers'  => ['view', 'create', 'update', 'delete'],
        'orders'     => ['view', 'create', 'update', 'delete'],
        'users'      => ['view', 'create', 'update', 'delete'],
        'roles'      => ['view', 'create', 'update', 'delete'],
        'waiters'    => ['view', 'create', 'update', 'delete'],
        'tables'     => ['view', 'create', 'update', 'delete'],
        'riders'     => ['view', 'create', 'update', 'delete'],
        'branches'   => ['view', 'create', 'update', 'delete'],
        'tenants'    => ['view', 'create', 'update', 'delete'],
        'reports'    => ['view', 'export'],
        'cash'       => ['view', 'open', 'close'],
        'audit'      => ['view'],
        'coupons'         => ['view', 'create', 'update', 'delete'],
        'modifiers'       => ['view', 'create', 'update', 'delete'],
        'delivery_zones'  => ['view', 'create', 'update', 'delete'],
        'suppliers'       => ['view', 'create', 'update', 'delete'],
        'purchase_orders' => ['view', 'create', 'update', 'delete', 'receive'],
        'time_entries'    => ['view', 'create', 'delete'],
        'fbr'             => ['view', 'retry'],
        'payroll'         => ['view', 'create', 'update', 'delete', 'pay'],
        'stock_adjustments' => ['view', 'create'],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create every permission as `{module}.{action}`
        foreach ($this->allPermissions() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Super-admin: every permission, lives outside any tenant. Reserved for the platform owner.
        $super = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $super->syncPermissions(Permission::all());

        // Admin (Store Admin): everything inside their tenant EXCEPT managing other tenants.
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::where('name', 'not like', 'tenants.%')->get());

        // Cashier: only POS-relevant view + order creation
        $cashier = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier->syncPermissions([
            'products.view',
            'categories.view',
            'customers.view',
            'customers.create',
            'orders.view',
            'orders.create',
            'orders.update',
            'waiters.view',
            'tables.view',
            'riders.view',
            'branches.view',
            'cash.view', 'cash.open', 'cash.close',
            // Cashier needs to read coupons/modifiers/zones to apply them at POS,
            // and create/view their own time entries.
            'coupons.view',
            'modifiers.view',
            'delivery_zones.view',
            'time_entries.view', 'time_entries.create',
            // Cashier may adjust stock at POS (e.g. spoilage during shift)
            'stock_adjustments.view', 'stock_adjustments.create',
        ]);

        // Editor: full CRUD on the catalog and customers, no orders/users/roles
        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $editor->syncPermissions([
            'products.view', 'products.create', 'products.update', 'products.delete',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'reports.view',
            // Editor manages catalog-adjacent settings: coupons, modifiers, suppliers, POs
            'coupons.view', 'coupons.create', 'coupons.update', 'coupons.delete',
            'modifiers.view', 'modifiers.create', 'modifiers.update', 'modifiers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.receive',
            // Editor (catalog manager) also manages stock adjustments
            'stock_adjustments.view', 'stock_adjustments.create',
        ]);
    }

    public function allPermissions(): array
    {
        $perms = [];
        foreach (self::MODULES as $module => $actions) {
            foreach ($actions as $action) {
                $perms[] = "{$module}.{$action}";
            }
        }
        return $perms;
    }
}
