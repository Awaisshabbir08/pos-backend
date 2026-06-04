<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds only the platform-level super-admin. Per-tenant users are created
 * via the Tenants admin flow (each tenant gets its own admin/cashier/editor).
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $super = User::firstOrCreate(
            ['email' => 'super@pos.com'],
            [
                'name'      => 'Super Admin',
                'password'  => Hash::make('password'),
                'status'    => 'active',
                'tenant_id' => null,
            ]
        );
        $super->syncRoles(['super-admin']);

        // Demo cashier + editor under the Default Store tenant, for trying
        // tenant-scoped permissions against the legacy demo data.
        $defaultStoreId = \DB::table('tenants')->where('slug', 'default-store')->value('id');

        if ($defaultStoreId) {
            $cashier = User::firstOrCreate(
                ['email' => 'cashier@pos.com'],
                [
                    'name'      => 'Demo Cashier',
                    'password'  => Hash::make('password'),
                    'status'    => 'active',
                    'tenant_id' => $defaultStoreId,
                ]
            );
            $cashier->syncRoles(['cashier']);

            $editor = User::firstOrCreate(
                ['email' => 'editor@pos.com'],
                [
                    'name'      => 'Demo Editor',
                    'password'  => Hash::make('password'),
                    'status'    => 'active',
                    'tenant_id' => $defaultStoreId,
                ]
            );
            $editor->syncRoles(['editor']);
        }
    }
}
