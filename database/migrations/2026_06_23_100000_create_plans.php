<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription plans with tiered per-branch pricing.
 *
 *   plans                  — the plan definition (Starter / Pro / Enterprise / custom)
 *   plan_pricing_tiers     — branch-range → price-per-branch rows
 *
 * Pricing model: for each branch a tenant owns, the system looks up which
 * tier that branch number falls into and adds that tier's price_per_branch
 * to the bill. Tiers naturally form volume discounts:
 *
 *   1-1   @ 5000   → first branch is expensive
 *   2-3   @ 4000   → next two cheaper
 *   4-NULL @ 2500  → 4th onwards is the cheapest (NULL = unlimited)
 *
 *   3 branches → 5000 + 4000 + 4000 = 13,000
 *   6 branches → 5000 + 4000+4000 + 2500*3 = 20,500
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('code', 50)->unique();     // 'starter', 'pro', 'enterprise', 'custom-1'
            $t->string('name', 100);
            $t->text('description')->nullable();
            $t->string('currency', 3)->default('PKR');
            $t->json('features')->nullable();     // ['reports', 'fbr', 'payroll', 'loyalty']
            $t->unsignedInteger('user_quota')->nullable();    // null = unlimited
            $t->enum('status', ['active', 'inactive'])->default('active');
            $t->timestamps();
        });

        Schema::create('plan_pricing_tiers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('min_branches');                          // inclusive (1, 2, 4)
            $t->unsignedInteger('max_branches')->nullable();              // inclusive; NULL = unlimited
            $t->decimal('price_per_branch', 12, 2);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();

            $t->index(['plan_id', 'min_branches']);
        });

        Schema::table('tenants', function (Blueprint $t) {
            $t->foreignId('plan_id')->nullable()->after('plan')->constrained('plans')->nullOnDelete();
            // 'plan' string column stays for now (legacy); plan_id is the new source of truth
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->dropForeign(['plan_id']);
            $t->dropColumn('plan_id');
        });
        Schema::dropIfExists('plan_pricing_tiers');
        Schema::dropIfExists('plans');
    }
};
