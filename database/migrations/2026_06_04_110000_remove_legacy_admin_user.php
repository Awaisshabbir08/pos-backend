<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove the legacy admin@pos.com demo user. With multi-tenancy each tenant
 * gets its own admin, created via the Tenants admin flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        $userId = DB::table('users')->where('email', 'admin@pos.com')->value('id');
        if (!$userId) return;

        // Clean up Spatie pivot rows first to avoid orphans
        DB::table('model_has_roles')
            ->where('model_type', \App\Models\User::class)
            ->where('model_id', $userId)
            ->delete();
        DB::table('model_has_permissions')
            ->where('model_type', \App\Models\User::class)
            ->where('model_id', $userId)
            ->delete();

        // Revoke any active tokens
        DB::table('personal_access_tokens')
            ->where('tokenable_type', \App\Models\User::class)
            ->where('tokenable_id', $userId)
            ->delete();

        DB::table('users')->where('id', $userId)->delete();
    }

    public function down(): void
    {
        // One-way: we can't recover the original password hash.
    }
};
