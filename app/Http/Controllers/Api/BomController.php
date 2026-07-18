<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BomItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BomController extends Controller
{
    /** List a product's recipe (BOM lines) with a computed recipe cost. */
    public function index(Product $product): JsonResponse
    {
        $items = $product->bomItems()->with('rawMaterial')->get();

        $recipeCost = $items->sum(fn ($i) => (float) $i->quantity * (float) ($i->rawMaterial->cost_per_unit ?? 0));

        return response()->json([
            'success' => true,
            'message' => 'Bill of materials retrieved successfully',
            'data'    => [
                'product_id'  => $product->id,
                'items'       => $items,
                'recipe_cost' => round($recipeCost, 2),
            ],
        ]);
    }

    /**
     * Replace a product's entire recipe with the supplied lines.
     * Body: { items: [ { raw_material_id, quantity }, ... ] }
     */
    public function sync(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'items'                   => 'present|array',
            'items.*.raw_material_id' => 'required|exists:raw_materials,id',
            'items.*.quantity'        => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($product, $data) {
            $product->bomItems()->delete();

            $seen = [];
            foreach ($data['items'] as $line) {
                $rmId = (int) $line['raw_material_id'];
                if (isset($seen[$rmId])) {
                    continue; // ignore duplicate raw materials, keep the first
                }
                $seen[$rmId] = true;

                BomItem::create([
                    'product_id'      => $product->id,
                    'raw_material_id' => $rmId,
                    'quantity'        => $line['quantity'],
                ]);
            }
        });

        $items = $product->bomItems()->with('rawMaterial')->get();
        $recipeCost = $items->sum(fn ($i) => (float) $i->quantity * (float) ($i->rawMaterial->cost_per_unit ?? 0));

        return response()->json([
            'success' => true,
            'message' => 'Bill of materials saved successfully',
            'data'    => [
                'product_id'  => $product->id,
                'items'       => $items,
                'recipe_cost' => round($recipeCost, 2),
            ],
        ]);
    }
}
