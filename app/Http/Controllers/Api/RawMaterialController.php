<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RawMaterialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RawMaterial::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->boolean('low_stock')) {
            $query->whereNotNull('low_stock_threshold')
                  ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
        }

        $query->orderBy('name');

        $materials = $request->boolean('all')
            ? $query->get()
            : $query->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'message' => 'Raw materials retrieved successfully',
            'data'    => $materials,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $material = RawMaterial::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Raw material created successfully',
            'data'    => $material,
        ], 201);
    }

    public function show(RawMaterial $rawMaterial): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Raw material retrieved successfully',
            'data'    => $rawMaterial,
        ]);
    }

    public function update(Request $request, RawMaterial $rawMaterial): JsonResponse
    {
        $data = $this->validated($request, true);
        $rawMaterial->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Raw material updated successfully',
            'data'    => $rawMaterial,
        ]);
    }

    public function destroy(RawMaterial $rawMaterial): JsonResponse
    {
        $rawMaterial->delete();

        return response()->json([
            'success' => true,
            'message' => 'Raw material deleted successfully',
            'data'    => null,
        ]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes|required' : 'required';
        return $request->validate([
            'name'                => "$req|string|max:255",
            'sku'                 => 'nullable|string|max:60',
            'unit'                => 'nullable|string|max:20',
            'cost_per_unit'       => 'nullable|numeric|min:0',
            'stock_quantity'      => 'nullable|numeric|min:0',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            'status'              => 'nullable|in:active,inactive',
        ]);
    }
}
