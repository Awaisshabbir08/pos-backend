<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTableRequest;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Table::query();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where('name', 'like', "%{$term}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);

        return response()->json([
            'success' => true,
            'message' => 'Tables retrieved successfully',
            'data'    => $query->orderBy('name')->paginate($perPage),
        ]);
    }

    public function store(StoreTableRequest $request): JsonResponse
    {
        $table = Table::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Table created successfully',
            'data'    => $table,
        ], 201);
    }

    public function show(Table $table): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Table retrieved successfully',
            'data'    => $table,
        ]);
    }

    public function update(StoreTableRequest $request, Table $table): JsonResponse
    {
        $table->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Table updated successfully',
            'data'    => $table,
        ]);
    }

    public function destroy(Table $table): JsonResponse
    {
        $table->delete();

        return response()->json([
            'success' => true,
            'message' => 'Table deleted successfully',
            'data'    => null,
        ]);
    }
}
