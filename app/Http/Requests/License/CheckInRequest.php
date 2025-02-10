<?php

namespace App\Http\Requests\License;

use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'license_key' => 'required|string',
            'domain' => 'required|string',
            'hardware_info' => 'nullable|array'
        ];
    }
}