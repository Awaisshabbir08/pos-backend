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

    public function destroy(Branch $branch): JsonResponse
    {
        $usageCount = $branch->waiters()->count()
                    + $branch->riders()->count()
                    + $branch->tables()->count()
                    + $branch->orders()->count()
                    + $branch->users()->count();

        if ($usageCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a branch that has staff, tables, orders or users assigned. Reassign them first.',
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
}
