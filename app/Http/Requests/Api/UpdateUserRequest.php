<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name'      => 'sometimes|required|string|max:255',
            'email'     => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password'  => 'nullable|string|min:6',
            'role'      => 'sometimes|required|string|exists:roles,name',
            'branch_id' => 'nullable|exists:branches,id',
            'status'    => 'nullable|in:active,inactive',
            'pay_type'       => 'nullable|in:hourly,salary,none',
            'hourly_rate'    => 'nullable|numeric|min:0',
            'monthly_salary' => 'nullable|numeric|min:0',
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
