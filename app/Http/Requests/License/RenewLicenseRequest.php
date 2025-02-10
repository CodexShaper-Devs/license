<?php

namespace App\Http\Requests\License;

use Illuminate\Foundation\Http\FormRequest;

class RenewLicenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'license_key' => 'required|string',
            'period' => 'required|in:yearly,lifetime',
            'seats' => 'nullable|integer|min:1'
        ];
    }
}