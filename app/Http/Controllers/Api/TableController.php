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
        $query = Table::with('branch');
        $this->applyBranchScope($query, $request);

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

    private function applyBranchScope($query, Request $request): void
    {
        $userBranch = $request->user()?->branch_id;
        if ($userBranch) {
            $query->where('branch_id', $userBranch);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
    }

    public function store(StoreTableRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['branch_id'] = $request->user()?->branch_id ?? ($data['branch_id'] ?? null);

        $table = Table::create($data);
        $table->load('branch');

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
        $data = $request->validated();
        if ($request->user()?->branch_id) {
            $data['branch_id'] = $request->user()->branch_id;
        }
        $table->update($data);
        $table->load('branch');

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
