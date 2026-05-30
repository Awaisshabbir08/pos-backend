<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('service_type', 'dine_in');

        return [
            'branch_id'         => 'nullable|exists:branches,id',
            'customer_id'       => 'nullable|exists:customers,id',
            'service_type'      => 'required|in:dine_in,take_away,delivery',
            'waiter_id'         => [$this->requireForDineInOrTakeaway($type), 'nullable', 'exists:waiters,id'],
            'table_id'          => [$type === 'dine_in' ? 'required' : 'nullable', 'exists:tables,id'],
            'rider_id'          => [$type === 'delivery' ? 'required' : 'nullable', 'exists:riders,id'],
            'tax_amount'        => 'nullable|numeric|min:0',
            'discount_amount'   => 'nullable|numeric|min:0',
            'paid_amount'       => 'required|numeric|min:0',
            'payment_method'    => 'required|in:cash,card,other',
            'notes'             => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.product_id'=> 'required|exists:products,id',
            'items.*.quantity'  => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'waiter_id.required' => 'A waiter is required for dine-in and take-away orders.',
            'table_id.required'  => 'A table is required for dine-in orders.',
            'rider_id.required'  => 'A rider is required for delivery orders.',
        ];
    }

    private function requireForDineInOrTakeaway(string $type): string
    {
        return in_array($type, ['dine_in', 'take_away']) ? 'required' : 'nullable';
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
