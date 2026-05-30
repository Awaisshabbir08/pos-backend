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
        'reports'    => ['view'],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create every permission as `{module}.{action}`
        foreach ($this->allPermissions() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Admin: full access
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        // Cashier: only POS-relevant view + order creation
        $cashier = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier->syncPermissions([
            'products.view',
            'categories.view',
            'customers.view',
            'customers.create',
            'orders.view',
            'orders.create',
            'waiters.view',
            'tables.view',
            'riders.view',
        ]);

        // Editor: full CRUD on the catalog and customers, no orders/users/roles
        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $editor->syncPermissions([
            'products.view', 'products.create', 'products.update', 'products.delete',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'reports.view',
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
