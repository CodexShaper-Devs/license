<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class LicenseValidationRequest extends BaseLicenseRequest
{
    public function rules(): array
    {
        return [
            'license_key' => ['required', 'string', 'min:16', 'max:64'],
            'domain' => [
                'required', 
                'string', 
                'regex:/^(?!:\/\/)(?=.{1,255}$)((.{1,63}\.){1,127}(?![0-9]*$)[a-z0-9-]+\.?)$/i'
            ],
            'environment' => [
                'nullable',
                Rule::in(['local', 'development', 'staging', 'production'])
            ],
            'device_identifier' => ['required', 'string', 'max:255'],
            'hardware_hash' => ['nullable', 'string', 'size:64'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'client_ip' => ['nullable', 'ip'],
            'ip_address' => ['required', 'ip'],
            'user_agent' => ['required', 'string', 'max:255'],
            'timestamp' => ['required', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }

    public function messages(): array
    {
        return [
            'domain.regex' => 'Invalid domain format.',
            'environment.in' => 'Environment must be one of: local, development, staging, production.',
            'hardware_hash.size' => 'Invalid hardware hash.',
        ];
    }

    protected function passedValidation(): void
    {
        // Add current context for validation
        $this->merge([
            'validation_context' => [
                'client_ip' => $this->input('client_ip', $this->ip()),
                'timestamp' => now()->timestamp,
                'check_type' => 'validation',
            ],
        ]);
    }
}