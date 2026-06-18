<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModifierGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModifierGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = ModifierGroup::with('modifiers');
        if ($request->filled('search')) $q->where('name', 'like', '%'.$request->search.'%');
        $perPage = $request->get('per_page', 50);
        return response()->json(['success'=>true,'message'=>'Modifier groups','data'=>$q->orderBy('name')->paginate($perPage)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'required'       => 'sometimes|boolean',
            'min_select'     => 'nullable|integer|min:0|max:99',
            'max_select'     => 'nullable|integer|min:1|max:99',
            'modifiers'      => 'sometimes|array',
            'modifiers.*.name'        => 'required|string|max:255',
            'modifiers.*.price_delta' => 'nullable|numeric',
            'product_ids'    => 'sometimes|array',
            'product_ids.*'  => 'integer|exists:products,id',
        ]);

        return DB::transaction(function () use ($data) {
            $group = ModifierGroup::create([
                'name'       => $data['name'],
                'required'   => $data['required'] ?? false,
                'min_select' => $data['min_select'] ?? 0,
                'max_select' => $data['max_select'] ?? 1,
            ]);
            foreach (($data['modifiers'] ?? []) as $m) {
                $group->modifiers()->create([
                    'tenant_id'   => $group->tenant_id,
                    'name'        => $m['name'],
                    'price_delta' => $m['price_delta'] ?? 0,
                ]);
            }
            if (!empty($data['product_ids'])) {
                $group->products()->sync($data['product_ids']);
            }
            return response()->json(['success'=>true,'message'=>'Modifier group created','data'=>$group->load('modifiers','products')], 201);
        });
    }

    public function show(ModifierGroup $modifierGroup): JsonResponse
    {
        return response()->json(['success'=>true,'message'=>'Modifier group','data'=>$modifierGroup->load('modifiers','products')]);
    }

    public function update(Request $request, ModifierGroup $modifierGroup): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'sometimes|required|string|max:255',
            'required'       => 'sometimes|boolean',
            'min_select'     => 'nullable|integer|min:0|max:99',
            'max_select'     => 'nullable|integer|min:1|max:99',
            'modifiers'      => 'sometimes|array',
            'modifiers.*.id'          => 'nullable|integer|exists:modifiers,id',
            'modifiers.*.name'        => 'required|string|max:255',
            'modifiers.*.price_delta' => 'nullable|numeric',
            'product_ids'    => 'sometimes|array',
            'product_ids.*'  => 'integer|exists:products,id',
        ]);

        return DB::transaction(function () use ($modifierGroup, $data) {
            $modifierGroup->update(collect($data)->only(['name', 'required', 'min_select', 'max_select'])->toArray());

            if (isset($data['modifiers'])) {
                $keepIds = [];
                foreach ($data['modifiers'] as $m) {
                    if (!empty($m['id'])) {
                        $existing = $modifierGroup->modifiers()->find($m['id']);
                        if ($existing) {
                            $existing->update(['name' => $m['name'], 'price_delta' => $m['price_delta'] ?? 0]);
                            $keepIds[] = $existing->id;
                        }
                    } else {
                        $created = $modifierGroup->modifiers()->create([
                            'tenant_id'   => $modifierGroup->tenant_id,
                            'name'        => $m['name'],
                            'price_delta' => $m['price_delta'] ?? 0,
                        ]);
                        $keepIds[] = $created->id;
                    }
                }
                $modifierGroup->modifiers()->whereNotIn('id', $keepIds)->delete();
            }

            if (isset($data['product_ids'])) {
                $modifierGroup->products()->sync($data['product_ids']);
            }

            return response()->json(['success'=>true,'message'=>'Modifier group updated','data'=>$modifierGroup->fresh(['modifiers','products'])]);
        });
    }

    public function destroy(ModifierGroup $modifierGroup): JsonResponse
    {
        $modifierGroup->delete();
        return response()->json(['success'=>true,'message'=>'Modifier group deleted','data'=>null]);
    }
}
