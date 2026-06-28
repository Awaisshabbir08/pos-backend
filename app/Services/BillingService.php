<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;

/**
 * Tiered per-branch billing.
 *
 * For each branch a tenant owns (numbered 1, 2, 3, …), find the matching tier
 * in the tenant's plan and add that tier's price_per_branch to the bill.
 * Tiers can overlap-free segment the branch space into volume-discount bands:
 *
 *   1-1   @ 5000   (first branch costs the most)
 *   2-3   @ 4000   (slight discount)
 *   4-NULL @ 2500  (cheapest beyond 4)
 */
class BillingService
{
    /**
     * Compute the monthly bill for a tenant based on its current branch count.
     * Returns a structured breakdown so the UI can show line items.
     */
    public function calculateForTenant(Tenant $tenant): array
    {
        if (!$tenant->plan_id) {
            return $this->emptyResult('No plan assigned');
        }
        $plan = Plan::with('pricingTiers')->find($tenant->plan_id);
        if (!$plan) {
            return $this->emptyResult('Plan not found');
        }

        $branchCount = $tenant->branches()->count();
        return $this->calculate($plan, $branchCount);
    }

    /**
     * Compute the monthly bill for a given plan + branch count. Useful for
     * "what if I had 4 branches" preview before actually creating them.
     */
    public function calculate(Plan $plan, int $branchCount): array
    {
        $tiers = $plan->pricingTiers()->orderBy('min_branches')->get();
        if ($tiers->isEmpty()) {
            return $this->emptyResult('Plan has no pricing tiers');
        }

        $perTierCount = [];   // tier_id => how many branches fall in this tier
        $unmatched = 0;

        for ($i = 1; $i <= $branchCount; $i++) {
            $matched = false;
            foreach ($tiers as $tier) {
                if ($tier->covers($i)) {
                    $perTierCount[$tier->id] = ($perTierCount[$tier->id] ?? 0) + 1;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) $unmatched++;
        }

        $lineItems = [];
        $total = 0;
        foreach ($tiers as $tier) {
            $count = $perTierCount[$tier->id] ?? 0;
            if ($count === 0) continue;
            $subtotal = $count * (float) $tier->price_per_branch;
            $total += $subtotal;
            $lineItems[] = [
                'tier_id'          => $tier->id,
                'range'            => $this->formatRange($tier->min_branches, $tier->max_branches),
                'branches_in_tier' => $count,
                'price_per_branch' => (float) $tier->price_per_branch,
                'subtotal'         => $subtotal,
            ];
        }

        return [
            'plan_id'         => $plan->id,
            'plan_name'       => $plan->name,
            'currency'        => $plan->currency,
            'branch_count'    => $branchCount,
            'unmatched_count' => $unmatched, // branches that fell outside every tier (config gap)
            'line_items'      => $lineItems,
            'total'           => round($total, 2),
            'note'            => $unmatched > 0
                ? "{$unmatched} branch(es) fell outside the plan's pricing tiers — extend the highest tier's max_branches to cover them."
                : null,
        ];
    }

    private function formatRange(int $min, ?int $max): string
    {
        if ($max === null) return "{$min}+";
        if ($min === $max) return "{$min}";
        return "{$min}-{$max}";
    }

    private function emptyResult(string $note): array
    {
        return [
            'plan_id'         => null,
            'plan_name'       => null,
            'currency'        => 'PKR',
            'branch_count'    => 0,
            'unmatched_count' => 0,
            'line_items'      => [],
            'total'           => 0,
            'note'            => $note,
        ];
    }
}
