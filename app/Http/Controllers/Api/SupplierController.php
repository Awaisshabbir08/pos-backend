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

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $force = $request->boolean('force');

        if ($force) {
            // Hard cleanup: drop the PO items, the POs, then the supplier.
            \DB::transaction(function () use ($supplier) {
                $poIds = \DB::table('purchase_orders')->where('supplier_id', $supplier->id)->pluck('id');
                if ($poIds->isNotEmpty()) {
                    \DB::table('purchase_order_items')->whereIn('purchase_order_id', $poIds)->delete();
                    \DB::table('purchase_orders')->where('supplier_id', $supplier->id)->delete();
                }
                $supplier->delete();
            });
            return response()->json([
                'success' => true,
                'message' => 'Supplier force-deleted; its purchase orders were removed too.',
                'data'    => null,
            ]);
        }

        try {
            $supplier->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete supplier — it has linked purchase orders. Add ?force=true to delete the POs and the supplier together, or cancel/archive the POs first.',
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
