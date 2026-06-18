<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = StockAdjustment::with(['product:id,name,sku', 'branch:id,name', 'user:id,name'])
            ->orderByDesc('created_at');
        if ($request->filled('branch_id'))  $q->where('branch_id', $request->branch_id);
        if ($request->filled('product_id')) $q->where('product_id', $request->product_id);
        if ($request->filled('type'))       $q->where('type', $request->type);
        return response()->json([
            'success' => true,
            'message' => 'Stock adjustments',
            'data'    => $q->paginate($request->get('per_page', 25)),
        ]);
    }

    /**
     * Apply a stock adjustment.
     *   type=set    → quantity is the NEW absolute count
     *   type=add    → quantity is added to current
     *   type=remove → quantity is subtracted (clamped at 0)
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'branch_id'  => 'nullable|exists:branches,id',
            'type'       => 'required|in:set,add,remove',
            'quantity'   => 'required|integer|min:0',
            'reason'     => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($data, $request) {
            $product = Product::findOrFail($data['product_id']);
            $branchId = $data['branch_id'] ?? $request->user()?->branch_id;

            $before = (int) $product->stockFor($branchId);
            $delta  = 0;
            $after  = $before;
            switch ($data['type']) {
                case 'set':
                    $after = (int) $data['quantity'];
                    $delta = $after - $before;
                    break;
                case 'add':
                    $delta = (int) $data['quantity'];
                    $after = $before + $delta;
                    break;
                case 'remove':
                    $delta = -((int) $data['quantity']);
                    $after = max(0, $before + $delta);
                    $delta = $after - $before;
                    break;
            }

            // Apply to branch_product pivot (or whole-product if no branch)
            if ($delta > 0)        $product->incrementStockFor($branchId, $delta);
            elseif ($delta < 0)    $product->decrementStockFor($branchId, abs($delta));

            $adj = StockAdjustment::create([
                'branch_id'       => $branchId,
                'product_id'      => $product->id,
                'user_id'         => $request->user()->id,
                'type'            => $data['type'],
                'quantity_change' => $delta,
                'quantity_before' => $before,
                'quantity_after'  => $after,
                'reason'          => $data['reason'] ?? null,
            ]);

            Audit::log('stock.adjust', $adj, [
                'product' => $product->name,
                'before'  => $before,
                'after'   => $after,
                'reason'  => $data['reason'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Stock adjusted: {$product->name} {$before} → {$after}",
                'data'    => $adj->load(['product:id,name,sku', 'branch:id,name', 'user:id,name']),
            ], 201);
        });
    }
}
