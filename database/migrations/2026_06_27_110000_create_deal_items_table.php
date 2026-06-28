<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A "deal" is a product (is_deal = true) sold at its own fixed price that
        // bundles several component products. deal_items lists those components.
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_deal')->default(false)->after('status');
        });

        Schema::create('deal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // The deal product this line belongs to.
            $table->foreignId('deal_product_id')->constrained('products')->cascadeOnDelete();
            // The component product included in the deal.
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('deal_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_items');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_deal');
        });
    }
};
