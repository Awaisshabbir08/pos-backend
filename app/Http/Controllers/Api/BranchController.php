<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBranchRequest;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Branch::query();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%{$term}%")
                                     ->orWhere('city', 'like', "%{$term}%"));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('all')) {
            return response()->json([
                'success' => true,
                'message' => 'Branches retrieved successfully',
                'data'    => $query->orderBy('name')->get(),
            ]);
        }

        $perPage = $request->get('per_page', 15);

        return response()->json([
            'success' => true,
            'message' => 'Branches retrieved successfully',
            'data'    => $query->orderBy('name')->paginate($perPage),
        ]);
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::create($this->normalize($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully',
            'data'    => $branch,
        ], 201);
    }

    public function show(Branch $branch): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Branch retrieved successfully',
            'data'    => $branch,
        ]);
    }

    public function update(StoreBranchRequest $request, Branch $branch): JsonResponse
    {
        $branch->update($this->normalize($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Branch updated successfully',
            'data'    => $branch,
        ]);
    }

    /**
     * Trim and normalise city/name so "Karachi ", "Karachi" and "karachi"
     * all group together. City is additionally title-cased to canonicalise case.
     */
    private function normalize(array $data): array
    {
        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = preg_replace('/\s+/u', ' ', trim($data['name']));
        }
        if (isset($data['city']) && is_string($data['city'])) {
            $city = preg_replace('/\s+/u', ' ', trim($data['city']));
            // Title case (mb-safe) so "lahore", "LAHORE", "Lahore" → "Lahore"
            $data['city'] = mb_convert_case($city, MB_CASE_TITLE, 'UTF-8');
        }
        return $data;
    }

    public function destroy(\Illuminate\Http\Request $request, Branch $branch): JsonResponse
    {
        $force = $request->boolean('force');

        if (!$force) {
            $usageCount = $branch->waiters()->count()
                        + $branch->riders()->count()
                        + $branch->tables()->count()
                        + $branch->orders()->count()
                        + $branch->users()->count();

            if ($usageCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a branch that has staff, tables, orders or users assigned. Reassign them first, or add ?force=true to unassign them and delete anyway.',
                    'data'    => null,
                ], 422);
            }

            $branch->delete();

            return response()->json([
                'success' => true,
                'message' => 'Branch deleted successfully',
                'data'    => null,
            ]);
        }

        // Force delete: null out references on every table that points to this
        // branch (staff move to "no branch", historical orders/cash keep their
        // data but lose the link), drop branch-scoped per-product stock, then
        // delete the branch row.
        \DB::transaction(function () use ($branch) {
            $bid = $branch->id;
            \DB::table('waiters')->where('branch_id', $bid)->update(['branch_id' => null]);
            \DB::table('riders')->where('branch_id', $bid)->update(['branch_id' => null]);
            \DB::table('tables')->where('branch_id', $bid)->update(['branch_id' => null]);
            \DB::table('users')->where('branch_id', $bid)->update(['branch_id' => null]);
            \DB::table('orders')->where('branch_id', $bid)->update(['branch_id' => null]);
            \DB::table('cash_registers')->where('branch_id', $bid)->update(['branch_id' => null]);
            \DB::table('purchase_orders')->where('branch_id', $bid)->update(['branch_id' => null]);
            \DB::table('delivery_zones')->where('branch_id', $bid)->update(['branch_id' => null]);
            \DB::table('time_entries')->where('branch_id', $bid)->update(['branch_id' => null]);
            \DB::table('stock_adjustments')->where('branch_id', $bid)->update(['branch_id' => null]);
            // branch_product is a true pivot — its rows are meaningless without the branch
            \DB::table('branch_product')->where('branch_id', $bid)->delete();
            \DB::table('branches')->where('id', $bid)->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Branch force-deleted; staff, orders and other references were unassigned.',
            'data'    => null,
        ]);
    }
}
