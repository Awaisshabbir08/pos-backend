<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CounterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Counter::query()->with('branch:id,name');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $query->orderBy('name');

        $counters = $request->boolean('all')
            ? $query->get()
            : $query->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'message' => 'Counters retrieved successfully',
            'data'    => $counters,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'name'      => 'required|string|max:255',
            'code'      => 'nullable|string|max:40',
            'status'    => 'nullable|in:active,inactive',
        ]);

        $counter = Counter::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Counter created successfully',
            'data'    => $counter->load('branch:id,name'),
        ], 201);
    }

    public function show(Counter $counter): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Counter retrieved successfully',
            'data'    => $counter->load('branch:id,name'),
        ]);
    }

    public function update(Request $request, Counter $counter): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'name'      => 'sometimes|required|string|max:255',
            'code'      => 'nullable|string|max:40',
            'status'    => 'nullable|in:active,inactive',
        ]);

        $counter->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Counter updated successfully',
            'data'    => $counter->load('branch:id,name'),
        ]);
    }

    public function destroy(Counter $counter): JsonResponse
    {
        $counter->delete();

        return response()->json([
            'success' => true,
            'message' => 'Counter deleted successfully',
            'data'    => null,
        ]);
    }
}
