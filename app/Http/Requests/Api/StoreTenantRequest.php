<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id        = $this->route('tenant')?->id ?? $this->route('tenant');
        $isCreate  = $this->isMethod('POST');
        $wantAdmin = $isCreate && $this->boolean('create_admin');

        return [
            'name'                    => 'required|string|max:255',
            'slug'                    => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('tenants', 'slug')->ignore($id)],
            'contact_email'           => 'nullable|email|max:255',
            'contact_phone'           => 'nullable|string|max:50',
            'plan'                    => 'nullable|in:basic,pro,enterprise',
            'plan_id'                 => 'nullable|exists:plans,id',
            'currency'                => 'nullable|string|size:3|regex:/^[A-Z]{3}$/',
            'logo'                    => 'nullable|string|max:1000',
            'receipt_header'          => 'nullable|string|max:1000',
            'receipt_footer'          => 'nullable|string|max:1000',
            'status'                  => 'nullable|in:active,inactive,trial',
            'subscription_expires_at' => 'nullable|date|after_or_equal:today',
            'notes'                   => 'nullable|string|max:2000',

            // FBR (Pakistan tax integration)
            'fbr_enabled'             => 'nullable|boolean',
            'fbr_ntn'                 => 'nullable|string|max:50',
            'fbr_pos_id'              => 'nullable|string|max:50',
            'fbr_token'               => 'nullable|string|max:4000',
            'fbr_endpoint'            => 'nullable|url|max:255',

            // Initial admin (only on create when create_admin = true)
            'create_admin'            => 'sometimes|boolean',
            'admin_name'              => [$wantAdmin ? 'required' : 'nullable', 'string', 'max:255'],
            'admin_email'             => [$wantAdmin ? 'required' : 'nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_password'          => [$wantAdmin ? 'required' : 'nullable', 'string', 'min:8', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex'                       => 'Slug may only contain lowercase letters, numbers and hyphens.',
            'subscription_expires_at.after_or_equal' => 'Subscription expiry cannot be in the past.',
            'admin_email.unique'               => 'A user with this email already exists. Pick a different email.',
            'admin_password.min'               => 'Admin password must be at least 8 characters.',
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
