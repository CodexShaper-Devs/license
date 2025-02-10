<?php

namespace App\Http\Requests\License;

use Illuminate\Foundation\Http\FormRequest;

class ActivateLicenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'license_key' => 'required|string',
            'domain' => 'required|string',
            'device_identifier' => 'nullable|string',
            'device_name' => 'nullable|string',
            'hardware_info' => 'nullable|array'
        ];
    }
}