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
        $type        = $this->input('service_type', 'dine_in');
        $isHolding   = $this->boolean('hold');
        // When split-payment is used (payments[] array supplied), the legacy
        // single-tender fields become optional — totals are derived from the array.
        $hasSplitPay = is_array($this->input('payments')) && count($this->input('payments')) > 0;
        $needLegacyPay = !$isHolding && !$hasSplitPay;

        return [
            'branch_id'              => 'nullable|exists:branches,id',
            'customer_id'            => 'nullable|exists:customers,id',
            'service_type'           => 'required|in:dine_in,take_away,delivery',
            'waiter_id'              => [$this->requireForDineInOrTakeaway($type), 'nullable', 'exists:waiters,id'],
            'table_id'               => [$type === 'dine_in' ? 'required' : 'nullable', 'exists:tables,id'],
            'rider_id'               => [$type === 'delivery' ? 'required' : 'nullable', 'exists:riders,id'],
            'tax_amount'             => 'nullable|numeric|min:0',
            'service_charge_amount'  => 'nullable|numeric|min:0',
            'discount_amount'        => 'nullable|numeric|min:0',
            'hold'                   => 'sometimes|boolean',
            // Legacy single-tender fields — required only when not holding AND not using split payment
            'paid_amount'            => [$needLegacyPay ? 'required' : 'nullable', 'numeric', 'min:0'],
            'payment_method'         => [$needLegacyPay ? 'required' : 'nullable', 'in:cash,card,easypaisa,jazzcash,bank,other'],
            'notes'                  => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|exists:products,id',
            'items.*.quantity'       => 'required|integer|min:1',
            'items.*.modifiers'      => 'sometimes|array',
            'items.*.modifiers.*'    => 'integer|exists:modifiers,id',
            'coupon_code'            => 'nullable|string|max:64',
            'delivery_zone_id'       => 'nullable|exists:delivery_zones,id',
            // Tips / gratuity
            'tip_amount'             => 'nullable|numeric|min:0',
            'tip_waiter_id'          => 'nullable|exists:waiters,id',
            // Split payment — array of {method, amount, reference?}
            'payments'                       => 'sometimes|array',
            'payments.*.method'              => 'required_with:payments|in:cash,card,easypaisa,jazzcash,bank,other',
            'payments.*.amount'              => 'required_with:payments|numeric|min:0',
            'payments.*.reference'           => 'nullable|string|max:100',
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
