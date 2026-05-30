<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRiderRequest;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RiderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Rider::query();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%{$term}%")
                                     ->orWhere('phone', 'like', "%{$term}%")
                                     ->orWhere('vehicle_number', 'like', "%{$term}%")
                                     ->orWhere('cnic_number', 'like', "%{$term}%"));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);

        return response()->json([
            'success' => true,
            'message' => 'Riders retrieved successfully',
            'data'    => $query->orderBy('name')->paginate($perPage),
        ]);
    }

    public function store(StoreRiderRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('riders', 'public');
        }
        if ($request->hasFile('cnic_image')) {
            $data['cnic_image'] = $request->file('cnic_image')->store('riders/cnic', 'public');
        }
        unset($data['remove_image'], $data['remove_cnic_image']);

        $rider = Rider::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Rider created successfully',
            'data'    => $rider,
        ], 201);
    }

    public function show(Rider $rider): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Rider retrieved successfully',
            'data'    => $rider,
        ]);
    }

    public function update(StoreRiderRequest $request, Rider $rider): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($rider->image) Storage::disk('public')->delete($rider->image);
            $data['image'] = $request->file('image')->store('riders', 'public');
        } elseif ($request->boolean('remove_image')) {
            if ($rider->image) Storage::disk('public')->delete($rider->image);
            $data['image'] = null;
        }

        if ($request->hasFile('cnic_image')) {
            if ($rider->cnic_image) Storage::disk('public')->delete($rider->cnic_image);
            $data['cnic_image'] = $request->file('cnic_image')->store('riders/cnic', 'public');
        } elseif ($request->boolean('remove_cnic_image')) {
            if ($rider->cnic_image) Storage::disk('public')->delete($rider->cnic_image);
            $data['cnic_image'] = null;
        }

        unset($data['remove_image'], $data['remove_cnic_image']);

        $rider->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Rider updated successfully',
            'data'    => $rider,
        ]);
    }

    public function destroy(Rider $rider): JsonResponse
    {
        if ($rider->image) Storage::disk('public')->delete($rider->image);
        if ($rider->cnic_image) Storage::disk('public')->delete($rider->cnic_image);

        $rider->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rider deleted successfully',
            'data'    => null,
        ]);
    }
}
