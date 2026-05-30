<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $branchId = $this->route('branch')?->id ?? $this->route('branch');

        return [
            'name'    => 'required|string|max:255',
            'city'    => 'nullable|string|max:255',
            'phone'   => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'status'  => 'nullable|in:active,inactive',
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
