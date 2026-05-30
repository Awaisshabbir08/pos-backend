<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreTableRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $tableId = $this->route('table')?->id ?? $this->route('table');

        return [
            'name'     => ['required', 'string', 'max:100', Rule::unique('tables', 'name')->ignore($tableId)],
            'capacity' => 'nullable|integer|min:1|max:99',
            'location' => 'nullable|string|max:100',
            'status'   => 'nullable|in:active,inactive',
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
