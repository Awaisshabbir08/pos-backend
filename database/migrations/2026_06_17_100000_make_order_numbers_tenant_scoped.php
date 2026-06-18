<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order number and PO number need to be unique PER TENANT, not globally.
 *
 * Before this migration, two tenants creating their first order on the same
 * day both tried to generate "ORD-YYYYMMDD-0001" → DB unique constraint
 * violation → 500 to the cashier. Same for purchase_orders.po_number.
 *
 * Switch the unique index from (column) to (tenant_id, column).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_order_number_unique');
            $table->unique(['tenant_id', 'order_number'], 'orders_tenant_order_number_unique');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('purchase_orders_po_number_unique');
            $table->unique(['tenant_id', 'po_number'], 'purchase_orders_tenant_po_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_tenant_order_number_unique');
            $table->unique('order_number');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('purchase_orders_tenant_po_number_unique');
            $table->unique('po_number');
        });
    }
};
