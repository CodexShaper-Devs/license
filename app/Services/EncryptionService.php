<?php

namespace App\Services;

use ParagonIE\HiddenString\HiddenString;
use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use ParagonIE\Halite\Symmetric\AuthenticationKey;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use RuntimeException;

class EncryptionService
{
    private const CURRENT_TIME = '2025-02-10 06:09:04';
    private const CURRENT_USER = 'maab16';

    public function __construct(
        private readonly KeyManagementService $keyManagement
    ) {}

    public function encrypt(array $data, string $keyId): HiddenString
    {
        try {
            // Get raw key material and convert to EncryptionKey
            $rawKey = $this->keyManagement->getEncryptionKey($keyId);
            $encryptionKey = new EncryptionKey(
                new HiddenString($rawKey)
            );

            $jsonData = json_encode($data, JSON_THROW_ON_ERROR);
            
            if ($jsonData === false) {
                throw new RuntimeException('Failed to encode data for encryption');
            }

            Log::debug('Encrypting data', [
                'keyId' => $keyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            $encrypted = Crypto::encrypt(
                new HiddenString($jsonData),
                $encryptionKey
            );

            return new HiddenString($encrypted);

        } catch (\Exception $e) {
            Log::error('Encryption failed', [
                'keyId' => $keyId,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            throw $e;
        }
    }

    public function decrypt(string $encrypted, string $keyId): HiddenString
    {
        try {
            // Get raw key material and convert to EncryptionKey
            $rawKey = $this->keyManagement->getEncryptionKey($keyId);
            $encryptionKey = new EncryptionKey(
                new HiddenString($rawKey)
            );

            Log::debug('Decrypting data', [
                'keyId' => $keyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return Crypto::decrypt(
                $encrypted,
                $encryptionKey
            );

        } catch (\Exception $e) {
            Log::error('Decryption failed', [
                'keyId' => $keyId,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            throw $e;
        }
    }

    public function sign(HiddenString $data, string $keyId): HiddenString
    {
        try {
            // Get raw key material and convert to AuthenticationKey
            $rawKey = $this->keyManagement->getAuthKey($keyId);
            $authKey = new AuthenticationKey(
                new HiddenString($rawKey)
            );

            Log::debug('Signing data', [
                'keyId' => $keyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            $signature = Crypto::authenticate(
                $data->getString(),
                $authKey
            );

            return new HiddenString($signature);

        } catch (\Exception $e) {
            Log::error('Signing failed', [
                'keyId' => $keyId,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            throw $e;
        }
    }

    public function verify(HiddenString $data, HiddenString $signature, string $keyId): bool
    {
        try {
            // Get raw key material and convert to AuthenticationKey
            $rawKey = $this->keyManagement->getAuthKey($keyId);
            $authKey = new AuthenticationKey(
                new HiddenString($rawKey)
            );

            Log::debug('Verifying signature', [
                'keyId' => $keyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return Crypto::verify(
                $data->getString(),
                $authKey,
                $signature->getString()
            );

        } catch (\Exception $e) {
            Log::error('Signature verification failed', [
                'keyId' => $keyId,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            return false;
        }
    }
}