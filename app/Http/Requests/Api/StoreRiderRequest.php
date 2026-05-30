<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRiderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'             => 'required|string|max:255',
            'phone'            => 'nullable|string|max:50',
            'vehicle_number'   => 'nullable|string|max:50',
            'cnic_number'      => 'nullable|string|max:50',
            'image'            => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'cnic_image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'remove_image'     => 'nullable|boolean',
            'remove_cnic_image'=> 'nullable|boolean',
            'status'           => 'nullable|in:active,inactive',
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
