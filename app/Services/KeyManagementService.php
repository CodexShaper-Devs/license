<?php

namespace App\Services;

use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use ParagonIE\Halite\Symmetric\AuthenticationKey;
use ParagonIE\HiddenString\HiddenString;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class KeyManagementService
{
    private const KEY_VERSION = 'v1';
    private const ENCRYPTION_DIR = 'keys/encryption';
    private const AUTH_DIR = 'keys/auth';
    private const METADATA_DIR = 'keys/metadata';

    public function __construct()
    {
        $this->ensureKeyDirectoriesExist();
    }

    private function getCurrentTimestamp(): string
    {
        return Carbon::now('UTC')->format('Y-m-d H:i:s');
    }

    private function getCurrentUser(): string
    {
        return Auth::user()->login ?? 'system';
    }

    public function generateKeys(string $keyId): array
    {
        try {
            Log::info('Starting key generation', [
                'keyId' => $keyId,
                'timestamp' => $this->getCurrentTimestamp(),
                'user' => $this->getCurrentUser()
            ]);

            // Generate random bytes for keys
            $encKeyBytes = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            $authKeyBytes = random_bytes(SODIUM_CRYPTO_AUTH_KEYBYTES);

            // Create keys from random bytes
            $encKey = new EncryptionKey(new HiddenString($encKeyBytes));
            $authKey = new AuthenticationKey(new HiddenString($authKeyBytes));

            // Store the keys
            $this->storeEncryptionKey($keyId, $encKeyBytes);
            $this->storeAuthenticationKey($keyId, $authKeyBytes);
            $this->storeKeyMetadata($keyId);

            Log::info('Keys generated successfully', [
                'keyId' => $keyId,
                'timestamp' => $this->getCurrentTimestamp()
            ]);

            return [
                'encryption_key' => $encKey,
                'auth_key' => $authKey
            ];
        } catch (\Exception $e) {
            Log::error('Key generation failed', [
                'keyId' => $keyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException('Failed to generate keys: ' . $e->getMessage());
        }
    }

    private function storeEncryptionKey(string $keyId, string $keyBytes): void
    {
        $path = sprintf('%s/%s/%s.key', self::ENCRYPTION_DIR, self::KEY_VERSION, $keyId);
        Storage::put($path, sodium_bin2hex($keyBytes));
    }

    private function storeAuthenticationKey(string $keyId, string $keyBytes): void
    {
        $path = sprintf('%s/%s/%s.key', self::AUTH_DIR, self::KEY_VERSION, $keyId);
        Storage::put($path, sodium_bin2hex($keyBytes));
    }

    private function storeKeyMetadata(string $keyId): void
    {
        $metadata = [
            'key_id' => $keyId,
            'version' => self::KEY_VERSION,
            'created_at' => $this->getCurrentTimestamp(),
            'created_by' => $this->getCurrentUser(),
            'encryption_algorithm' => 'XSALSA20',
            'authentication_algorithm' => 'HMACSHA512256',
            'status' => 'active'
        ];

        $path = sprintf('%s/%s/%s.json', self::METADATA_DIR, self::KEY_VERSION, $keyId);
        Storage::put($path, json_encode($metadata, JSON_PRETTY_PRINT));
    }

    public function getEncryptionKey(string $keyId): EncryptionKey
    {
        try {
            $path = sprintf('%s/%s/%s.key', self::ENCRYPTION_DIR, self::KEY_VERSION, $keyId);
            if (!Storage::exists($path)) {
                throw new \RuntimeException("Encryption key not found: {$keyId}");
            }
            
            $keyHex = Storage::get($path);
            $keyBytes = sodium_hex2bin($keyHex);
            
            return new EncryptionKey(new HiddenString($keyBytes));
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to load encryption key: ' . $e->getMessage());
        }
    }

    public function getAuthKey(string $keyId): AuthenticationKey
    {
        try {
            $path = sprintf('%s/%s/%s.key', self::AUTH_DIR, self::KEY_VERSION, $keyId);
            if (!Storage::exists($path)) {
                throw new \RuntimeException("Authentication key not found: {$keyId}");
            }
            
            $keyHex = Storage::get($path);
            $keyBytes = sodium_hex2bin($keyHex);
            
            return new AuthenticationKey(new HiddenString($keyBytes));
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to load authentication key: ' . $e->getMessage());
        }
    }

    private function ensureKeyDirectoriesExist(): void
    {
        $directories = [
            self::ENCRYPTION_DIR,
            self::AUTH_DIR,
            self::METADATA_DIR
        ];

        foreach ($directories as $directory) {
            $path = storage_path('app/' . $directory . '/' . self::KEY_VERSION);
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
}