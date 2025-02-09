<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivateLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add your authorization logic here
    }

    public function rules(): array
    {
        return [
            'license_key' => 'required|string',
            'domain' => 'required|string',
            'environment' => 'required|string',
            'client_signature' => 'required|string',
            'metadata' => 'nullable|array'
        ];
    }
}