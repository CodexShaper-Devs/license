<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\AuthenticationKey;
use ParagonIE\Halite\Symmetric\EncryptionKey;

class LicenseGeneratorService
{
    protected $encryptionKey;
    protected $authKey;

    public function __construct()
    {
        $this->initializeKeys();
    }

    protected function initializeKeys()
    {
        // Generate or load encryption keys
        $keyPath = storage_path('app/keys');
        if (!file_exists($keyPath)) {
            mkdir($keyPath, 0700, true);
        }

        $encKeyPath = $keyPath . '/encryption.key';
        $authKeyPath = $keyPath . '/auth.key';

        if (!file_exists($encKeyPath)) {
            $this->encryptionKey = KeyFactory::generateEncryptionKey();
            KeyFactory::save($this->encryptionKey, $encKeyPath);
        } else {
            $this->encryptionKey = KeyFactory::loadEncryptionKey($encKeyPath);
        }

        if (!file_exists($authKeyPath)) {
            $this->authKey = KeyFactory::generateAuthenticationKey();
            KeyFactory::save($this->authKey, $authKeyPath);
        } else {
            $this->authKey = KeyFactory::loadAuthenticationKey($authKeyPath);
        }
    }

    public function generateLicense(array $data): string
    {
        $entropy = random_bytes(config('licensing.license.key_entropy'));
        $uniqueId = bin2hex($entropy);
        
        $licenseData = [
            'id' => $uniqueId,
            'created_at' => now()->toIso8601String(),
            'data' => $data,
            'hardware_id' => $this->generateHardwareId($data),
        ];

        // Sign the license data
        $signature = $this->signLicenseData($licenseData);
        $licenseData['signature'] = $signature;

        // Encrypt the entire license
        return $this->encryptLicense($licenseData);
    }

    protected function generateHardwareId(array $data): string
    {
        if (!config('licensing.license.hardware_binding')) {
            return '';
        }

        $hardwareInfo = [
            'cpu' => $data['cpu_id'] ?? '',
            'disk' => $data['disk_id'] ?? '',
            'mac' => $data['mac_address'] ?? '',
        ];

        return hash('sha256', json_encode($hardwareInfo));
    }

    protected function signLicenseData(array $data): string
    {
        $dataToSign = json_encode($data);
        return sodium_crypto_auth($dataToSign, $this->authKey->getRawKeyMaterial());
    }

    protected function encryptLicense(array $data): string
    {
        $jsonData = json_encode($data);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        
        $encrypted = sodium_crypto_secretbox(
            $jsonData,
            $nonce,
            $this->encryptionKey->getRawKeyMaterial()
        );

        return base64_encode($nonce . $encrypted);
    }
}