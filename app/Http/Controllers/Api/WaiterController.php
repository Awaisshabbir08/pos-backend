<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreWaiterRequest;
use App\Models\Waiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WaiterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Waiter::query();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%{$term}%")
                                     ->orWhere('phone', 'like', "%{$term}%")
                                     ->orWhere('cnic_number', 'like', "%{$term}%"));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);

        return response()->json([
            'success' => true,
            'message' => 'Waiters retrieved successfully',
            'data'    => $query->orderBy('name')->paginate($perPage),
        ]);
    }

    public function store(StoreWaiterRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('waiters', 'public');
        }
        if ($request->hasFile('cnic_image')) {
            $data['cnic_image'] = $request->file('cnic_image')->store('waiters/cnic', 'public');
        }
        unset($data['remove_image'], $data['remove_cnic_image']);

        $waiter = Waiter::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Waiter created successfully',
            'data'    => $waiter,
        ], 201);
    }

    public function show(Waiter $waiter): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Waiter retrieved successfully',
            'data'    => $waiter,
        ]);
    }

    public function update(StoreWaiterRequest $request, Waiter $waiter): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($waiter->image) Storage::disk('public')->delete($waiter->image);
            $data['image'] = $request->file('image')->store('waiters', 'public');
        } elseif ($request->boolean('remove_image')) {
            if ($waiter->image) Storage::disk('public')->delete($waiter->image);
            $data['image'] = null;
        }

        if ($request->hasFile('cnic_image')) {
            if ($waiter->cnic_image) Storage::disk('public')->delete($waiter->cnic_image);
            $data['cnic_image'] = $request->file('cnic_image')->store('waiters/cnic', 'public');
        } elseif ($request->boolean('remove_cnic_image')) {
            if ($waiter->cnic_image) Storage::disk('public')->delete($waiter->cnic_image);
            $data['cnic_image'] = null;
        }

        unset($data['remove_image'], $data['remove_cnic_image']);

        $waiter->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Waiter updated successfully',
            'data'    => $waiter,
        ]);
    }

    public function destroy(Waiter $waiter): JsonResponse
    {
        if ($waiter->image) Storage::disk('public')->delete($waiter->image);
        if ($waiter->cnic_image) Storage::disk('public')->delete($waiter->cnic_image);

        $waiter->delete();

        return response()->json([
            'success' => true,
            'message' => 'Waiter deleted successfully',
            'data'    => null,
        ]);
    }
}
