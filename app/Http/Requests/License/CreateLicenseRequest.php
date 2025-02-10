<?php

namespace App\Http\Requests\License;

use Illuminate\Foundation\Http\FormRequest;

class CreateLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'plan_id' => 'required|exists:license_plans,id',
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:subscription,lifetime,trial',
            'source' => 'required|in:custom,envato',
            'source_purchase_code' => 'required_if:source,envato',
            'domain' => 'required|string',
            'purchased_seats' => 'required_if:type,subscription|integer|min:1',
            'trial_days' => 'required_if:type,trial|integer|min:1',
            'features' => 'nullable|array',
            'restrictions' => 'nullable|array',
            'metadata' => 'nullable|array'
        ];
    }
}