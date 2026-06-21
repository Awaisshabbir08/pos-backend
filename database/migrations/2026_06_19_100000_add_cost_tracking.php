<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cost tracking:
 *   - products.cost_price — what you paid the supplier (current weighted-avg cost)
 *   - order_items.unit_cost_at_sale — snapshot of cost when the line was sold,
 *     so historical COGS / margin reports don't shift when cost changes later
 *   - product_cost_history — every cost change is logged with source + actor
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->decimal('cost_price', 10, 2)->default(0)->after('price');
        });

        Schema::table('order_items', function (Blueprint $t) {
            $t->decimal('unit_cost_at_sale', 10, 2)->nullable()->after('unit_price');
        });

        Schema::create('product_cost_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->decimal('previous_cost', 10, 2)->nullable();
            $t->decimal('cost_price', 10, 2);
            $t->enum('source', ['initial', 'manual', 'po_receive', 'import'])->default('manual');
            // when source=po_receive, source_id points to purchase_orders.id
            $t->unsignedBigInteger('source_id')->nullable();
            $t->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('notes', 500)->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_cost_history');
        Schema::table('order_items', function (Blueprint $t) {
            $t->dropColumn('unit_cost_at_sale');
        });
        Schema::table('products', function (Blueprint $t) {
            $t->dropColumn('cost_price');
        });
    }
};
