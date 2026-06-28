<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? $this->route('product');

        return [
            'category_id'    => 'nullable|exists:categories,id',
            'name'           => 'sometimes|required|string|max:255',
            'sku'            => ['sometimes', 'required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'description'    => 'nullable|string',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_image'   => 'nullable|boolean',
            'price'          => 'sometimes|required|numeric|min:0',
            'cost_price'     => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'reorder_point'  => 'nullable|integer|min:0',
            'status'         => 'nullable|in:active,inactive',
            // Optional sized variants (e.g. Small/Medium/Large), each with its own price.
            'variants'              => 'sometimes|array',
            'variants.*.id'         => 'nullable|integer',
            'variants.*.name'       => 'nullable|string|max:100',
            'variants.*.price'      => 'nullable|numeric|min:0',
            'variants.*.sort_order' => 'nullable|integer|min:0',
            'variants.*.status'     => 'nullable|in:active,inactive',
            // Deal / combo: a fixed-price bundle of component products.
            'is_deal'                 => 'sometimes|boolean',
            'deal_items'              => 'sometimes|array',
            'deal_items.*.product_id' => 'nullable|integer|exists:products,id',
            'deal_items.*.quantity'   => 'nullable|integer|min:1',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'data'    => $validator->errors(),
        ], 422));
    }
}
