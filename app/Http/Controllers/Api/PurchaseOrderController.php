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

    /**
     * Mark a PO as received: increment stock per line item AND update each
     * product's cost_price using a weighted-average formula:
     *
     *   new_cost = (current_stock × current_cost + received_qty × po_unit_cost)
     *              / (current_stock + received_qty)
     *
     * When current_stock or current_cost is 0, the PO's unit_cost wins.
     * Every cost change is logged in product_cost_history with source='po_receive'.
     */
    public function receive(\Illuminate\Http\Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status === 'received') {
            return response()->json(['success'=>false,'message'=>'Already received','data'=>null], 422);
        }
        if ($purchaseOrder->status === 'cancelled') {
            return response()->json(['success'=>false,'message'=>'Cannot receive a cancelled PO','data'=>null], 422);
        }

        return DB::transaction(function () use ($purchaseOrder, $request) {
            $userId = $request->user()?->id;
            foreach ($purchaseOrder->items as $item) {
                $product = Product::find($item->product_id);
                if (!$product) continue;

                $currentStock = (int) $product->stockFor($purchaseOrder->branch_id);
                $currentCost  = (float) $product->cost_price;
                $poQty        = (int) $item->quantity;
                $poCost       = (float) $item->unit_cost;

                // Compute weighted average. If we had no prior stock or no prior
                // cost, the new cost is just the PO's unit cost.
                $newCost = ($currentStock <= 0 || $currentCost <= 0)
                    ? $poCost
                    : ($currentStock * $currentCost + $poQty * $poCost) / ($currentStock + $poQty);
                $newCost = round($newCost, 2);

                // Apply stock first
                $product->incrementStockFor($purchaseOrder->branch_id, $poQty);

                // Then cost — write history if it moved
                if (abs($newCost - $currentCost) > 0.005) {
                    \App\Models\ProductCostHistory::create([
                        'tenant_id'          => $product->tenant_id,
                        'product_id'         => $product->id,
                        'previous_cost'      => $currentCost,
                        'cost_price'         => $newCost,
                        'source'             => 'po_receive',
                        'source_id'          => $purchaseOrder->id,
                        'changed_by_user_id' => $userId,
                        'notes'              => "Weighted avg from PO {$purchaseOrder->po_number}: prev stock {$currentStock} @ {$currentCost}, received {$poQty} @ {$poCost}",
                    ]);
                    $product->forceFill(['cost_price' => $newCost])->save();
                }
            }
            $purchaseOrder->update(['status' => 'received', 'received_at' => now()]);
            Audit::log('purchase_order.receive', $purchaseOrder);
            return response()->json(['success'=>true,'message'=>'PO received, stock + cost updated','data'=>$purchaseOrder->fresh(['items.product'])]);
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

    /**
     * Delete a purchase order.
     *
     * - Default (no force): only `draft` and `cancelled` POs are deletable.
     *   Received POs are refused with 422 because they affected stock.
     * - `?force=true`: any status. We do NOT reverse the stock change — too risky
     *   to assume what should happen (refund? write-off?). The audit log keeps
     *   the trail of the original receive event.
     */
    public function destroy(\Illuminate\Http\Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $force = $request->boolean('force');

        if (!$force && $purchaseOrder->status === 'received') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a received PO (it already affected stock). Add ?force=true to delete anyway — the stock change will NOT be reversed.',
                'data'    => null,
            ], 422);
        }

        \DB::transaction(function () use ($purchaseOrder) {
            \DB::table('purchase_order_items')->where('purchase_order_id', $purchaseOrder->id)->delete();
            $purchaseOrder->delete();
        });

        Audit::log('purchase_order.delete', $purchaseOrder, ['force' => $force, 'final_status' => $purchaseOrder->status]);

        return response()->json(['success'=>true,'message'=>'Purchase order deleted','data'=>null]);
    }

    private function generatePoNumber(): string
    {
        $prefix = 'PO-' . now()->format('Ymd') . '-';
        $last = PurchaseOrder::where('po_number', 'like', $prefix.'%')->orderByDesc('id')->value('po_number');
        $next = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;
        return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }
}
