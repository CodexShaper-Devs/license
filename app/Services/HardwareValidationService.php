<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Facades\Cache;

class HardwareValidationService
{
    public function validate(License $license, array $hardwareInfo): bool
    {
        if (empty($hardwareInfo)) {
            return false;
        }

        $hash = $this->generateHardwareHash($hardwareInfo);
        $activation = $license->activations()
            ->where('hardware_hash', $hash)
            ->where('is_active', true)
            ->first();

        if (!$activation) {
            return $this->canActivateNewHardware($license, $hash);
        }

        return $this->validateExistingActivation($activation);
    }

    public function generateHardwareHash(array $hardwareInfo): string
    {
        // Sort to ensure consistent hash
        ksort($hardwareInfo);
        
        $uniqueIdentifiers = array_filter([
            $hardwareInfo['cpu_id'] ?? null,
            $hardwareInfo['disk_id'] ?? null,
            $hardwareInfo['mac_address'] ?? null,
            $hardwareInfo['bios_id'] ?? null,
        ]);

        return hash('sha256', implode('|', $uniqueIdentifiers));
    }

    protected function canActivateNewHardware(License $license, string $hash): bool
    {
        if (!$license->hasAvailableSeats()) {
            return false;
        }

        $cacheKey = "hardware_activation_attempt:{$license->id}:{$hash}";
        $attempts = Cache::get($cacheKey, 0);

        if ($attempts >= 3) {
            return false;
        }

        Cache::put($cacheKey, $attempts + 1, now()->addHours(24));
        return true;
    }

    protected function validateExistingActivation($activation): bool
    {
        if (!$activation->is_active) {
            return false;
        }

        // Check if the activation hasn't expired
        if ($activation->last_check_in->diffInDays(now()) > 30) {
            $activation->update(['is_active' => false]);
            return false;
        }

        // Update last check-in time
        $activation->update([
            'last_check_in' => now(),
            'check_in_count' => $activation->check_in_count + 1
        ]);

        return true;
    }

    public function deactivateHardware(License $license, string $hardwareHash): bool
    {
        return $license->activations()
            ->where('hardware_hash', $hardwareHash)
            ->update(['is_active' => false]) > 0;
    }

    public function getActivationStatus(License $license, string $hardwareHash): array
    {
        $activation = $license->activations()
            ->where('hardware_hash', $hardwareHash)
            ->first();

        return [
            'is_active' => $activation?->is_active ?? false,
            'last_check_in' => $activation?->last_check_in,
            'check_in_count' => $activation?->check_in_count ?? 0,
            'device_name' => $activation?->device_name,
        ];
    }
}