<?php

namespace App\Services;

use App\Exceptions\LicenseValidationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\AuthenticationKey;
use ParagonIE\Halite\Symmetric\EncryptionKey;

class LicenseValidatorService
{
    protected $encryptionKey;
    protected $authKey;

    public function __construct()
    {
        $this->loadKeys();
    }

    protected function loadKeys()
    {
        $keyPath = storage_path('app/keys');
        $this->encryptionKey = KeyFactory::loadEncryptionKey($keyPath . '/encryption.key');
        $this->authKey = KeyFactory::loadAuthenticationKey($keyPath . '/auth.key');
    }

    public function validateLicense(string $licenseKey, array $currentHardwareInfo = []): bool
    {
        try {
            $decrypted = $this->decryptLicense($licenseKey);
            
            if (!$this->verifySignature($decrypted)) {
                throw new LicenseValidationException('Invalid license signature');
            }

            if (!$this->validateHardwareBinding($decrypted, $currentHardwareInfo)) {
                throw new LicenseValidationException('Hardware verification failed');
            }

            if (!$this->validateTimestamp($decrypted)) {
                throw new LicenseValidationException('License has expired');
            }

            $this->updateValidationCache($decrypted['id']);
            return true;

        } catch (\Exception $e) {
            Log::error('License validation failed: ' . $e->getMessage());
            return false;
        }
    }

    protected function decryptLicense(string $licenseKey): array
    {
        $decoded = base64_decode($licenseKey);
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $encrypted = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        $decrypted = sodium_crypto_secretbox_open(
            $encrypted,
            $nonce,
            $this->encryptionKey->getRawKeyMaterial()
        );

        if ($decrypted === false) {
            throw new LicenseValidationException('Failed to decrypt license');
        }

        return json_decode($decrypted, true);
    }

    protected function verifySignature(array $data): bool
    {
        $signature = $data['signature'];
        unset($data['signature']);
        
        $dataToVerify = json_encode($data);
        
        try {
            return sodium_crypto_auth_verify(
                $signature,
                $dataToVerify,
                $this->authKey->getRawKeyMaterial()
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function validateHardwareBinding(array $license, array $currentHardware): bool
    {
        if (!config('licensing.license.hardware_binding')) {
            return true;
        }

        $currentHardwareId = hash('sha256', json_encode($currentHardware));
        return hash_equals($license['hardware_id'], $currentHardwareId);
    }

    protected function validateTimestamp(array $license): bool
    {
        $lastValidation = Cache::get('license_validation_' . $license['id']);
        $gracePeriod = config('licensing.license.grace_period') * 3600;

        if ($lastValidation && (time() - $lastValidation) > $gracePeriod) {
            return false;
        }

        return true;
    }

    protected function updateValidationCache(string $licenseId): void
    {
        Cache::put(
            'license_validation_' . $licenseId,
            time(),
            now()->addHours(config('licensing.license.grace_period'))
        );
    }
}