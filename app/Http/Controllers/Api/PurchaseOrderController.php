<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = PurchaseOrder::with(['supplier', 'branch', 'createdBy', 'items.product']);
        if ($request->filled('status'))      $q->where('status', $request->status);
        if ($request->filled('supplier_id')) $q->where('supplier_id', $request->supplier_id);
        if ($request->filled('branch_id'))   $q->where('branch_id', $request->branch_id);
        $perPage = $request->get('per_page', 15);
        return response()->json(['success'=>true,'message'=>'Purchase orders','data'=>$q->orderByDesc('created_at')->paginate($perPage)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'        => 'required|exists:suppliers,id',
            'branch_id'          => 'nullable|exists:branches,id',
            'expected_at'        => 'nullable|date',
            'notes'              => 'nullable|string|max:2000',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_cost'  => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request, $data) {
            $total = 0;
            foreach ($data['items'] as $i) $total += $i['quantity'] * $i['unit_cost'];

            $po = PurchaseOrder::create([
                'supplier_id'        => $data['supplier_id'],
                'branch_id'          => $data['branch_id'] ?? $request->user()?->branch_id,
                'created_by_user_id' => $request->user()->id,
                'po_number'          => $this->generatePoNumber(),
                'status'             => 'draft',
                'expected_at'        => $data['expected_at'] ?? null,
                'notes'              => $data['notes'] ?? null,
                'total_amount'       => $total,
            ]);

            foreach ($data['items'] as $i) {
                $po->items()->create([
                    'product_id' => $i['product_id'],
                    'quantity'   => $i['quantity'],
                    'unit_cost'  => $i['unit_cost'],
                    'subtotal'   => $i['quantity'] * $i['unit_cost'],
                ]);
            }

            Audit::log('purchase_order.create', $po);
            return response()->json(['success'=>true,'message'=>'PO created','data'=>$po->load(['supplier','branch','items.product'])], 201);
        });
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json([
            'success'=>true,'message'=>'PO',
            'data'=>$purchaseOrder->load(['supplier','branch','createdBy','items.product']),
        ]);
    }

    /** Mark a PO as received and increment stock for each line item. */
    public function receive(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status === 'received') {
            return response()->json(['success'=>false,'message'=>'Already received','data'=>null], 422);
        }
        if ($purchaseOrder->status === 'cancelled') {
            return response()->json(['success'=>false,'message'=>'Cannot receive a cancelled PO','data'=>null], 422);
        }

        return DB::transaction(function () use ($purchaseOrder) {
            foreach ($purchaseOrder->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) $product->incrementStockFor($purchaseOrder->branch_id, (int) $item->quantity);
            }
            $purchaseOrder->update(['status' => 'received', 'received_at' => now()]);
            Audit::log('purchase_order.receive', $purchaseOrder);
            return response()->json(['success'=>true,'message'=>'PO received and stock updated','data'=>$purchaseOrder->fresh(['items.product'])]);
        });
    }

    public function cancel(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status === 'received') {
            return response()->json(['success'=>false,'message'=>'Cannot cancel a received PO','data'=>null], 422);
        }
        $purchaseOrder->update(['status' => 'cancelled']);
        Audit::log('purchase_order.cancel', $purchaseOrder);
        return response()->json(['success'=>true,'message'=>'PO cancelled','data'=>$purchaseOrder]);
    }

    private function generatePoNumber(): string
    {
        $prefix = 'PO-' . now()->format('Ymd') . '-';
        $last = PurchaseOrder::where('po_number', 'like', $prefix.'%')->orderByDesc('id')->value('po_number');
        $next = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;
        return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }
}
