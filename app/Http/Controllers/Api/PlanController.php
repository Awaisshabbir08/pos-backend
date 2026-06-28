<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    public function __construct(private BillingService $billing) {}

    public function index(): JsonResponse
    {
        $plans = Plan::with('pricingTiers')->orderBy('name')->get();
        return response()->json([
            'success' => true,
            'message' => 'Plans',
            'data'    => $plans,
        ]);
    }

    public function show(Plan $plan): JsonResponse
    {
        $plan->load('pricingTiers');
        return response()->json([
            'success' => true,
            'message' => 'Plan',
            'data'    => $plan,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        return DB::transaction(function () use ($data) {
            $plan = Plan::create([
                'code'        => $data['code'],
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'currency'    => $data['currency'] ?? 'PKR',
                'features'    => $data['features'] ?? [],
                'user_quota'  => $data['user_quota'] ?? null,
                'status'      => $data['status'] ?? 'active',
            ]);
            $this->syncTiers($plan, $data['tiers']);
            return response()->json([
                'success' => true,
                'message' => 'Plan created',
                'data'    => $plan->load('pricingTiers'),
            ], 201);
        });
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $data = $this->validatePayload($request, $plan->id);
        return DB::transaction(function () use ($plan, $data) {
            $plan->update([
                'code'        => $data['code'],
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'currency'    => $data['currency'] ?? 'PKR',
                'features'    => $data['features'] ?? [],
                'user_quota'  => $data['user_quota'] ?? null,
                'status'      => $data['status'] ?? 'active',
            ]);
            $this->syncTiers($plan, $data['tiers']);
            return response()->json([
                'success' => true,
                'message' => 'Plan updated',
                'data'    => $plan->load('pricingTiers'),
            ]);
        });
    }

    public function destroy(Plan $plan): JsonResponse
    {
        if ($plan->tenants()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a plan that has tenants assigned. Move them to another plan first.',
                'data'    => null,
            ], 422);
        }
        $plan->delete();
        return response()->json(['success' => true, 'message' => 'Plan deleted', 'data' => null]);
    }

    /**
     * Preview the bill for a (plan, branch_count) pair — used in the tenant
     * create modal to show "if you go with Pro and 3 branches, it'll be …".
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id'      => 'required|exists:plans,id',
            'branch_count' => 'required|integer|min:0',
        ]);
        $plan = Plan::with('pricingTiers')->find($data['plan_id']);
        return response()->json([
            'success' => true,
            'message' => 'Bill preview',
            'data'    => $this->billing->calculate($plan, (int) $data['branch_count']),
        ]);
    }

    /** Current bill for one tenant (uses its actual branch count). */
    public function forTenant(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Tenant bill',
            'data'    => $this->billing->calculateForTenant($tenant),
        ]);
    }

    private function validatePayload(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code'        => ['required', 'string', 'max:50',
                              \Illuminate\Validation\Rule::unique('plans', 'code')->ignore($id)],
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'currency'    => 'nullable|string|size:3',
            'features'    => 'nullable|array',
            'features.*'  => 'string|max:50',
            'user_quota'  => 'nullable|integer|min:1',
            'status'      => 'nullable|in:active,inactive',
            'tiers'                          => 'required|array|min:1',
            'tiers.*.min_branches'           => 'required|integer|min:1',
            'tiers.*.max_branches'           => 'nullable|integer|min:1',
            'tiers.*.price_per_branch'       => 'required|numeric|min:0',
            'tiers.*.sort_order'             => 'nullable|integer',
        ]);
    }

    private function syncTiers(Plan $plan, array $tiers): void
    {
        $plan->pricingTiers()->delete();
        foreach (array_values($tiers) as $idx => $row) {
            $plan->pricingTiers()->create([
                'min_branches'     => (int) $row['min_branches'],
                'max_branches'     => isset($row['max_branches']) && $row['max_branches'] !== '' ? (int) $row['max_branches'] : null,
                'price_per_branch' => (float) $row['price_per_branch'],
                'sort_order'       => $row['sort_order'] ?? $idx,
            ]);
        }
    }
}
