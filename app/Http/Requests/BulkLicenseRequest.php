<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkLicenseRequest extends FormRequest
{
    private const MAX_BULK_SIZE = 100;
    private const TIMESTAMP = '2025-02-09 07:31:18';
    private const USER = 'maab16';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'licenses' => 'required|array|min:1|max:' . self::MAX_BULK_SIZE,
            'licenses.*.source' => ['required', 'string', Rule::in(['custom', 'envato'])],
            'licenses.*.type' => ['required', 'string', Rule::in(['subscription', 'onetime'])],
            'licenses.*.product_id' => 'required|uuid|exists:products,id',
            'licenses.*.user_id' => 'required|exists:users,id',
            'licenses.*.features' => 'nullable|array',
            'licenses.*.features.*' => 'string',
            'licenses.*.valid_from' => 'required|date',
            'licenses.*.valid_until' => 'required_if:licenses.*.type,subscription|nullable|date|after:licenses.*.valid_from',
            'licenses.*.restrictions' => 'nullable|array',
            'licenses.*.restrictions.domain' => 'nullable|string',
            'licenses.*.restrictions.environment' => 'nullable|string',
            'licenses.*.metadata' => 'nullable|array',
            
            // Envato specific validations
            'licenses.*.source_purchase_code' => [
                'required_if:licenses.*.source,envato',
                'nullable',
                'string',
                'regex:/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
                Rule::unique('licenses', 'source_purchase_code')
                    ->where('source', 'envato')
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'licenses.*.source_purchase_code.regex' => 'The purchase code must be a valid Envato purchase code format.',
            'licenses.*.source_purchase_code.unique' => 'This purchase code has already been registered.',
            'licenses.max' => 'Maximum of ' . self::MAX_BULK_SIZE . ' licenses can be processed at once.'
        ];
    }

    protected function prepareForValidation(): void
    {
        $licenses = $this->input('licenses', []);
        
        $licenses = array_map(function ($license) {
            return array_merge($license, [
                'created_at' => self::TIMESTAMP,
                'created_by' => self::USER,
                'updated_at' => self::TIMESTAMP,
                'updated_by' => self::USER
            ]);
        }, $licenses);

        $this->merge(['licenses' => $licenses]);
    }
}