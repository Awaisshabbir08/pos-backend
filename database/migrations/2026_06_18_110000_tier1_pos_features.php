<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 1 POS features:
 *   1. stock_adjustments — manual stock count log per (product, branch).
 *   2. order_payments    — split-payment support (multiple tenders per order).
 *   3. orders.tip_*      — gratuity attached to an order, attributable to a waiter.
 *   4. products.reorder_point — low-stock threshold for dashboard alerts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->enum('type', ['set', 'add', 'remove']);
            $t->integer('quantity_change');     // signed: +n for add, -n for remove, delta for set
            $t->integer('quantity_before');
            $t->integer('quantity_after');
            $t->string('reason', 500)->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'product_id']);
            $t->index(['tenant_id', 'branch_id']);
        });

        Schema::create('order_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->enum('method', ['cash', 'card', 'easypaisa', 'jazzcash', 'bank', 'other'])->default('cash');
            $t->decimal('amount', 12, 2);
            $t->string('reference', 100)->nullable(); // card slip #, txn id, etc.
            $t->timestamps();

            $t->index(['tenant_id', 'order_id']);
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->decimal('tip_amount', 10, 2)->default(0)->after('discount_amount');
            $t->foreignId('tip_waiter_id')->nullable()->after('tip_amount')->constrained('waiters')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $t) {
            $t->unsignedInteger('reorder_point')->default(0)->after('stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->dropColumn('reorder_point');
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->dropForeign(['tip_waiter_id']);
            $t->dropColumn(['tip_amount', 'tip_waiter_id']);
        });
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('stock_adjustments');
    }
};
