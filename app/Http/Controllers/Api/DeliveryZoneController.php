<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryZoneController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = DeliveryZone::with('branch');
        if ($request->filled('branch_id')) $q->where('branch_id', $request->branch_id);
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->boolean('all')) {
            return response()->json(['success'=>true,'message'=>'Zones','data'=>$q->orderBy('name')->get()]);
        }
        $perPage = $request->get('per_page', 50);
        return response()->json(['success'=>true,'message'=>'Zones','data'=>$q->orderBy('name')->paginate($perPage)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request);
        $zone = DeliveryZone::create($data);
        return response()->json(['success'=>true,'message'=>'Zone created','data'=>$zone], 201);
    }

    public function update(Request $request, DeliveryZone $deliveryZone): JsonResponse
    {
        $deliveryZone->update($this->validate($request));
        return response()->json(['success'=>true,'message'=>'Zone updated','data'=>$deliveryZone->fresh()]);
    }

    public function destroy(DeliveryZone $deliveryZone): JsonResponse
    {
        $deliveryZone->delete();
        return response()->json(['success'=>true,'message'=>'Zone deleted','data'=>null]);
    }

    private function validate(Request $request): array
    {
        return $request->validate([
            'branch_id'         => 'nullable|exists:branches,id',
            'name'              => 'required|string|max:255',
            'fee'               => 'required|numeric|min:0',
            'estimated_minutes' => 'nullable|integer|min:0|max:300',
            'status'            => 'nullable|in:active,inactive',
        ]);
    }
}
