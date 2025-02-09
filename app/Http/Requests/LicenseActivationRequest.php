<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class LicenseActivationRequest extends BaseLicenseRequest
{
    public function rules(): array
    {
        return [
            'license_key' => ['required', 'string', 'min:16', 'max:64'],
            'device_identifier' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:255'],
            'hardware' => ['required', 'array'],
            'hardware.cpu_id' => ['required', 'string', 'max:255'],
            'hardware.disk_id' => ['required', 'string', 'max:255'],
            'hardware.mac_address' => [
                'required', 
                'string', 
                'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'
            ],
            'domain' => [
                'required', 
                'string', 
                'regex:/^(?!:\/\/)(?=.{1,255}$)((.{1,63}\.){1,127}(?![0-9]*$)[a-z0-9-]+\.?)$/i'
            ],
            'metadata' => ['nullable', 'array'],
            'metadata.os' => ['nullable', 'string', 'max:255'],
            'metadata.app_version' => ['nullable', 'string', 'max:50'],
            'metadata.php_version' => ['nullable', 'string', 'max:50'],
            'metadata.environment' => [
                'nullable',
                Rule::in(['local', 'development', 'staging', 'production'])
            ],
            'ip_address' => ['required', 'ip'],
            'user_agent' => ['required', 'string', 'max:255'],
            'timestamp' => ['required', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }

    public function messages(): array
    {
        return [
            'hardware.cpu_id.required' => 'CPU identifier is required for hardware validation.',
            'hardware.disk_id.required' => 'Disk identifier is required for hardware validation.',
            'hardware.mac_address.regex' => 'Invalid MAC address format.',
            'domain.regex' => 'Invalid domain format.',
            'metadata.environment.in' => 'Environment must be one of: local, development, staging, production.',
        ];
    }

    protected function passedValidation(): void
    {
        // Hash hardware identifiers for consistency
        $this->merge([
            'hardware_hash' => hash('sha256', json_encode([
                'cpu' => $this->input('hardware.cpu_id'),
                'disk' => $this->input('hardware.disk_id'),
                'mac' => $this->input('hardware.mac_address'),
            ])),
        ]);
    }
}