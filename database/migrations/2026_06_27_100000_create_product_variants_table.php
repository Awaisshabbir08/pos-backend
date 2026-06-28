<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A product variant is a sellable configuration of a product (e.g. a
        // pizza in Small / Medium / Large) that carries its OWN price. This is
        // distinct from modifiers, which are add-ons applied as price deltas.
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // Small / Medium / Large
            $table->decimal('price', 10, 2)->default(0);     // fixed price for this variant
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });

        // Snapshot the chosen variant on each order line so historical orders
        // keep showing the size/price that was sold even if the variant is
        // later renamed, repriced, or deleted.
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')
                  ->constrained('product_variants')->nullOnDelete();
            $table->string('variant_name')->nullable()->after('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn(['product_variant_id', 'variant_name']);
        });
        Schema::dropIfExists('product_variants');
    }
};
