<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Supplier::query();
        if ($request->filled('search')) $q->where('name', 'like', '%'.$request->search.'%');
        if ($request->boolean('all')) {
            return response()->json(['success'=>true,'message'=>'Suppliers','data'=>$q->orderBy('name')->get()]);
        }
        $perPage = $request->get('per_page', 50);
        return response()->json(['success'=>true,'message'=>'Suppliers','data'=>$q->orderBy('name')->paginate($perPage)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request);
        $supplier = Supplier::create($data);
        return response()->json(['success'=>true,'message'=>'Supplier created','data'=>$supplier], 201);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($this->validate($request));
        return response()->json(['success'=>true,'message'=>'Supplier updated','data'=>$supplier->fresh()]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        try {
            $supplier->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete supplier — it has linked purchase orders. Cancel or archive those first.',
                    'data'    => null,
                ], 422);
            }
            throw $e;
        }
        return response()->json(['success'=>true,'message'=>'Supplier deleted','data'=>null]);
    }

    private function validate(Request $request): array
    {
        return $request->validate([
            'name'           => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string',
            'status'         => 'nullable|in:active,inactive',
        ]);
    }
}
