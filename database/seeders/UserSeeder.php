<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@pos.com'],
            [
                'name'     => 'Admin User',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $admin->syncRoles(['admin']);

        // Cashier
        $cashier = User::firstOrCreate(
            ['email' => 'cashier@pos.com'],
            [
                'name'     => 'John Cashier',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $cashier->syncRoles(['cashier']);

        // Editor
        $editor = User::firstOrCreate(
            ['email' => 'editor@pos.com'],
            [
                'name'     => 'Jane Editor',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $editor->syncRoles(['editor']);
    }
}
