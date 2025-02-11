<?php

namespace App\Http\Requests\Envato;

use Illuminate\Foundation\Http\FormRequest;

class ActivateLicenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'domain' => 'required|string|max:255',
            'device_identifier' => 'required|string|max:255',
            'device_name' => 'required|string|max:255',
            'hardware_info' => 'required|array',
            'hardware_info.cpu' => 'required|string',
            'hardware_info.ram' => 'required|string',
            'hardware_info.os' => 'required|string',
            'hardware_info.mac_address' => 'required|string'
        ];
    }
}