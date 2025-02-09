<?php

namespace App\Http\Requests;

class LicenseDeactivationRequest extends BaseLicenseRequest
{
    public function rules(): array
    {
        return [
            'license_key' => ['required', 'string', 'min:16', 'max:64'],
            'device_identifier' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'deactivation_type' => [
                'nullable',
                'string',
                'in:user_requested,system,expired,violated,transferred'
            ],
            'hardware_hash' => ['nullable', 'string', 'size:64'],
            'ip_address' => ['required', 'ip'],
            'user_agent' => ['required', 'string', 'max:255'],
            'timestamp' => ['required', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }

    public function messages(): array
    {
        return [
            'deactivation_type.in' => 'Invalid deactivation type.',
            'hardware_hash.size' => 'Invalid hardware hash.',
        ];
    }

    protected function passedValidation(): void
    {
        // Set default deactivation type if not provided
        if (!$this->has('deactivation_type')) {
            $this->merge([
                'deactivation_type' => 'user_requested',
            ]);
        }

        // Add metadata for logging
        $this->merge([
            'deactivation_metadata' => [
                'timestamp' => now()->timestamp,
                'ip_address' => $this->ip(),
                'user_agent' => $this->userAgent(),
                'reason' => $this->input('reason'),
            ],
        ]);
    }
}