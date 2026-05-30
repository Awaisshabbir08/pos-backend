<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Map every legacy permission name onto the new granular set.
     */
    private array $legacyMap = [
        'manage-products'   => ['products.view', 'products.create', 'products.update', 'products.delete'],
        'manage-categories' => ['categories.view', 'categories.create', 'categories.update', 'categories.delete'],
        'manage-customers'  => ['customers.view', 'customers.create', 'customers.update', 'customers.delete'],
        'manage-orders'     => ['orders.view', 'orders.create', 'orders.update', 'orders.delete'],
        'create-orders'     => ['orders.view', 'orders.create'],
        'view-reports'      => ['reports.view'],
        'manage-users'      => [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
        ],
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $seeder = new RolesAndPermissionsSeeder();
        foreach ($seeder->allPermissions() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Translate each role's old permissions into the granular ones
        Role::with('permissions')->get()->each(function (Role $role): void {
            $granular = collect();

            foreach ($role->permissions as $perm) {
                if (isset($this->legacyMap[$perm->name])) {
                    $granular = $granular->merge($this->legacyMap[$perm->name]);
                } elseif (str_contains($perm->name, '.')) {
                    $granular->push($perm->name);
                }
            }

            $granular = $granular->unique()->values()->all();

            if ($role->name === 'admin') {
                $role->syncPermissions(Permission::all());
            } else {
                $role->syncPermissions($granular);
            }
        });

        // Drop the legacy permissions now that they are no longer referenced
        Permission::whereIn('name', array_keys($this->legacyMap))->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // One-way data migration; no rollback.
    }
};
