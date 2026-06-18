<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the status enum to support the new lifecycle states.
        DB::statement("ALTER TABLE orders MODIFY status ENUM('pending','held','completed','cancelled','voided','refunded') NOT NULL DEFAULT 'pending'");

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('held_at')->nullable()->after('status');
            $table->timestamp('voided_at')->nullable()->after('held_at');
            $table->timestamp('refunded_at')->nullable()->after('voided_at');
            $table->string('void_reason')->nullable()->after('refunded_at');
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('void_reason');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['held_at', 'voided_at', 'refunded_at', 'void_reason', 'refunded_amount']);
        });
        DB::statement("ALTER TABLE orders MODIFY status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
