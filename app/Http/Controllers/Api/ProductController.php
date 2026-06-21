<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProductRequest;
use App\Http\Requests\Api\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCostHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $with = ['category'];
        if ($request->boolean('with_modifiers')) {
            $with[] = 'modifierGroups.modifiers';
        }
        $query = Product::with($with);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        $perPage = $request->get('per_page', 15);
        $products = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data'    => $products,
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);

        // Seed cost history with the initial cost (even if 0 — gives reports a baseline).
        if (array_key_exists('cost_price', $data)) {
            ProductCostHistory::create([
                'tenant_id'          => $product->tenant_id,
                'product_id'         => $product->id,
                'previous_cost'      => null,
                'cost_price'         => $product->cost_price ?? 0,
                'source'             => 'initial',
                'changed_by_user_id' => $request->user()?->id,
                'notes'              => 'Set on product create',
            ]);
        }

        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data'    => $product,
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'modifierGroups.modifiers']);

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully',
            'data'    => $product,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        } elseif ($request->boolean('remove_image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = null;
        }

        unset($data['remove_image']);

        // Track cost changes — write a history row whenever cost_price moves
        // by more than 0.01. Done before update() so we can read the previous cost.
        $previousCost = (float) $product->cost_price;
        $newCost      = array_key_exists('cost_price', $data) ? (float) $data['cost_price'] : null;
        $costChanged  = $newCost !== null && abs($previousCost - $newCost) > 0.005;

        $product->update($data);

        if ($costChanged) {
            ProductCostHistory::create([
                'tenant_id'          => $product->tenant_id,
                'product_id'         => $product->id,
                'previous_cost'      => $previousCost,
                'cost_price'         => $newCost,
                'source'             => 'manual',
                'changed_by_user_id' => $request->user()?->id,
                'notes'              => $request->input('cost_change_note'),
            ]);
        }

        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data'    => $product,
        ]);
    }

    /** Cost-over-time for a product. */
    public function costHistory(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Cost history',
            'data'    => $product->costHistory()->with('changedBy:id,name')->paginate(50),
        ]);
    }

    public function destroy(\Illuminate\Http\Request $request, Product $product): JsonResponse
    {
        // Delete semantics:
        //   no flag → try hard delete; 422 with can_archive hint if it has order history
        //   ?archive=true → SMART: hard-delete when there's no order history (so the
        //       product is truly gone); soft-delete (status=inactive) only when there
        //       IS order history, so the audit trail keeps pointing at something
        //   ?force=true → ignore everything, attempt hard delete (will fail if FK
        //       enforcement is active and the product is still referenced)
        $archive    = $request->boolean('archive');
        $force      = $request->boolean('force');
        $hasHistory = $product->orderItems()->exists();

        // If history exists and the caller isn't forcing or archiving, refuse with
        // an actionable error.
        if ($hasHistory && !$archive && !$force) {
            return response()->json([
                'success' => false,
                'message' => 'This product has been ordered. Use Archive to mark it inactive (keeps order history), or Force Delete to wipe it.',
                'data'    => ['can_archive' => true],
            ], 422);
        }

        // Soft-delete path: only when the product genuinely has order history.
        // If there's NO history (e.g. user deleted all orders, or it was never
        // sold), fall through to hard delete even with ?archive=true — that's
        // what the user means when they click delete on an archived product.
        if ($archive && $hasHistory) {
            $product->update(['status' => 'inactive']);
            return response()->json([
                'success' => true,
                'message' => 'Product archived (marked inactive). Order history is preserved.',
                'data'    => null,
            ]);
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        try {
            \DB::transaction(function () use ($product) {
                // Wipe sibling-table references that don't have ON DELETE CASCADE
                // so the product row can actually be removed. order_items is the
                // only one we DON'T touch — its existence already gated this path.
                $pid = $product->id;
                \DB::table('purchase_order_items')->where('product_id', $pid)->delete();
                \DB::table('branch_product')->where('product_id', $pid)->delete();
                \DB::table('product_modifier_group')->where('product_id', $pid)->delete();
                \DB::table('stock_adjustments')->where('product_id', $pid)->delete();
                \DB::table('product_cost_history')->where('product_id', $pid)->delete();
                $product->delete();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product is referenced by orders. Use Archive to mark it inactive instead.',
                    'data'    => ['can_archive' => true],
                ], 422);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
            'data'    => null,
        ]);
    }

    /**
     * List products at or below their reorder_point — feeds the dashboard alert widget.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $q = Product::with('category')
            ->where('reorder_point', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'reorder_point')
            ->orderBy('stock_quantity');
        $perPage = $request->get('per_page', 50);
        return response()->json([
            'success' => true,
            'message' => 'Low stock products',
            'data'    => $q->paginate($perPage),
        ]);
    }

    /**
     * Bulk CSV import.
     * Accepts a CSV with header row: name,sku,price,stock_quantity,reorder_point,category,description
     * `category` is matched by name (case-insensitive); if it doesn't exist, the row falls back to no category.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $rows = [];
        $created = 0; $updated = 0; $skipped = 0;
        $errors = [];

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (!$handle) {
            return response()->json(['success'=>false,'message'=>'Could not read uploaded file','data'=>null], 422);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return response()->json(['success'=>false,'message'=>'Empty CSV file','data'=>null], 422);
        }
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) continue; // blank row

            $r = array_combine(array_pad($header, count($row), null), $row) ?: [];
            $name = trim($r['name'] ?? '');
            $sku  = trim($r['sku'] ?? '');
            if ($name === '' || $sku === '') {
                $skipped++;
                $errors[] = ['line' => $line, 'reason' => 'Missing name or sku'];
                continue;
            }

            $price = is_numeric($r['price'] ?? null) ? (float) $r['price'] : 0;
            $stock = is_numeric($r['stock_quantity'] ?? null) ? (int) $r['stock_quantity'] : 0;
            $reord = is_numeric($r['reorder_point'] ?? null) ? (int) $r['reorder_point'] : 0;
            $desc  = trim($r['description'] ?? '');
            $catName = trim($r['category'] ?? '');
            $categoryId = null;
            if ($catName !== '') {
                $cat = \App\Models\Category::whereRaw('LOWER(name) = ?', [strtolower($catName)])->first();
                $categoryId = $cat?->id;
            }

            $data = [
                'name'           => $name,
                'price'          => $price,
                'stock_quantity' => $stock,
                'reorder_point'  => $reord,
                'description'    => $desc ?: null,
                'category_id'    => $categoryId,
                'status'         => 'active',
            ];

            $existing = Product::where('sku', $sku)->first();
            if ($existing) {
                $existing->update($data);
                $updated++;
                $rows[] = ['line' => $line, 'sku' => $sku, 'action' => 'updated', 'id' => $existing->id];
            } else {
                $data['sku'] = $sku;
                $new = Product::create($data);
                $created++;
                $rows[] = ['line' => $line, 'sku' => $sku, 'action' => 'created', 'id' => $new->id];
            }
        }
        fclose($handle);

        return response()->json([
            'success' => true,
            'message' => "Imported: {$created} created, {$updated} updated, {$skipped} skipped.",
            'data'    => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'rows'    => $rows,
                'errors'  => $errors,
            ],
        ]);
    }
}
