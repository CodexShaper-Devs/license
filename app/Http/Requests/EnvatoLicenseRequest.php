<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnvatoLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_code' => [
                'required',
                'string',
                'regex:/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
                Rule::unique('licenses', 'source_purchase_code')
                    ->where('source', 'envato')
            ],
            'product_id' => 'required|uuid|exists:products,id',
            'user_id' => 'required|exists:users,id',
            'features' => 'nullable|array',
            'features.*' => 'string',
            'restrictions' => 'nullable|array',
            'restrictions.domain' => 'nullable|string',
            'restrictions.environment' => 'nullable|string',
            'metadata' => 'nullable|array',
            'metadata.support_email' => 'nullable|email',
            'metadata.buyer_email' => 'nullable|email',
            'metadata.item_id' => 'nullable|string'
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_code.regex' => 'The purchase code must be a valid Envato purchase code format.',
            'purchase_code.unique' => 'This purchase code has already been registered.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source' => 'envato',
            'type' => 'onetime',
            'valid_from' => '2025-02-09 07:28:26'
        ]);
    }
}