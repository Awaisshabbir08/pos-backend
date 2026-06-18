<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProductRequest;
use App\Http\Requests\Api\UpdateProductRequest;
use App\Models\Product;
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

        $product->update($data);
        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data'    => $product,
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        if ($product->orderItems()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete product that has been ordered',
                'data'    => null,
            ], 422);
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

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
