<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization can be handled via middleware
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ip_address' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}