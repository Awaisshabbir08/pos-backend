<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds tenant_id to every tenant-owned table and assigns existing rows to a
 * single "Default Store" tenant so nothing breaks during the transition.
 */
return new class extends Migration
{
    private array $tables = [
        'users',
        'branches',
        'categories',
        'products',
        'customers',
        'orders',
        'order_items',
        'waiters',
        'tables',
        'riders',
    ];

    public function up(): void
    {
        // Seed a default tenant for existing data
        $defaultId = DB::table('tenants')->insertGetId([
            'name'          => 'Default Store',
            'slug'          => 'default-store',
            'plan'          => 'enterprise',
            'status'        => 'active',
            'contact_email' => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        foreach ($this->tables as $tableName) {
            // 1) add nullable column + index
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')
                      ->constrained('tenants')->cascadeOnDelete();
                $table->index('tenant_id');
            });

            // 2) backfill all existing rows to the default tenant
            DB::table($tableName)->update(['tenant_id' => $defaultId]);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex([$tableName . '_tenant_id_index']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
