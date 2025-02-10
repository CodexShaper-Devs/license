<?php

namespace App\Services;

use App\Services\Storage\KeyStorageService;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use ParagonIE\Halite\Symmetric\AuthenticationKey;
use ParagonIE\HiddenString\HiddenString;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use RuntimeException;

class KeyManagementService
{
    private const CURRENT_TIME = '2025-02-10 06:12:34';
    private const CURRENT_USER = 'maab16';

    public function __construct(
        private readonly KeyStorageService $keyStorage
    ) {}

    public function generateKeys(string $keyId): array
    {
        try {
            Log::info('Starting key generation', [
                'keyId' => $keyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            // Generate raw key material for encryption
            $rawEncryptionKey = sodium_crypto_secretbox_keygen();
            $encryptionKey = new EncryptionKey(
                new HiddenString($rawEncryptionKey)
            );

            // Store encryption key
            $this->keyStorage->put(
                "keys/encryption/{$keyId}.key",
                sodium_bin2hex($rawEncryptionKey)
            );
            
            Log::info('Encryption key stored successfully', [
                'keyId' => $keyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            // Generate raw key material for authentication
            $rawAuthKey = sodium_crypto_auth_keygen();
            $authKey = new AuthenticationKey(
                new HiddenString($rawAuthKey)
            );

            // Store auth key
            $this->keyStorage->put(
                "keys/auth/{$keyId}.key",
                sodium_bin2hex($rawAuthKey)
            );
            
            Log::info('Auth key stored successfully', [
                'keyId' => $keyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return [
                'encryption_key' => $rawEncryptionKey,
                'auth_key' => $rawAuthKey
            ];

        } catch (\Exception $e) {
            Log::error('Key generation failed', [
                'error' => $e->getMessage(),
                'keyId' => $keyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            throw new RuntimeException('Failed to generate keys: ' . $e->getMessage());
        }
    }

    public function getEncryptionKey(string $keyId): string
    {
        try {
            $hexKey = $this->keyStorage->get("keys/encryption/{$keyId}.key");
            return sodium_hex2bin($hexKey);
        } catch (\Exception $e) {
            Log::error('Failed to get encryption key', [
                'keyId' => $keyId,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            throw new RuntimeException('Failed to get encryption key: ' . $e->getMessage());
        }
    }

    public function getAuthKey(string $keyId): string
    {
        try {
            $hexKey = $this->keyStorage->get("keys/auth/{$keyId}.key");
            return sodium_hex2bin($hexKey);
        } catch (\Exception $e) {
            Log::error('Failed to get auth key', [
                'keyId' => $keyId,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            throw new RuntimeException('Failed to get auth key: ' . $e->getMessage());
        }
    }

    public function hasEncryptionKey(string $keyId): bool
    {
        return $this->keyStorage->exists("keys/encryption/{$keyId}.key");
    }

    public function hasAuthKey(string $keyId): bool
    {
        return $this->keyStorage->exists("keys/auth/{$keyId}.key");
    }

    public function hasKeys(string $keyId): bool
    {
        return $this->hasEncryptionKey($keyId) && $this->hasAuthKey($keyId);
    }
}