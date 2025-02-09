<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\License;

class CreateLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add your authorization logic here
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:subscription,onetime',
            'product_id' => 'required|uuid',
            'user_id' => 'required|exists:users,id',
            'seats' => 'required|integer|min:1',
            'features' => 'required|array',
            'valid_from' => 'required|date',
            'valid_until' => 'required_if:type,subscription|nullable|date|after:valid_from',
            'restrictions' => 'nullable|array',
            'restrictions.domain' => 'nullable|string',
            'restrictions.environment' => 'nullable|string',
            'metadata' => 'nullable|array'
        ];
    }
}