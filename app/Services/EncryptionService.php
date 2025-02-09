<?php

namespace App\Services;

use ParagonIE\HiddenString\HiddenString;
use ParagonIE\Halite\Symmetric\Crypto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EncryptionService
{
    public function __construct(
        private readonly KeyManagementService $keyManagement
    ) {}

    public function encrypt(HiddenString $data, string $keyId): string
    {
        try {
            $key = $this->keyManagement->getEncryptionKey($keyId);
            Log::debug('Encrypting data', ['keyId' => $keyId]);
            return Crypto::encrypt($data, $key);
        } catch (\Exception $e) {
            Log::error('Encryption failed', [
                'keyId' => $keyId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Encryption failed: ' . $e->getMessage());
        }
    }

    public function decrypt(string $encrypted, string $keyId): HiddenString
    {
        try {
            $key = $this->keyManagement->getEncryptionKey($keyId);
            Log::debug('Decrypting data', ['keyId' => $keyId]);
            return Crypto::decrypt($encrypted, $key);
        } catch (\Exception $e) {
            Log::error('Decryption failed', [
                'keyId' => $keyId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Decryption failed: ' . $e->getMessage());
        }
    }

    public function sign(HiddenString $data, string $keyId): string
    {
        try {
            $key = $this->keyManagement->getAuthKey($keyId);
            Log::debug('Signing data', ['keyId' => $keyId]);
            return Crypto::authenticate($data->getString(), $key);
        } catch (\Exception $e) {
            Log::error('Signing failed', [
                'keyId' => $keyId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Signing failed: ' . $e->getMessage());
        }
    }

    public function verify(HiddenString $data, string $signature, string $keyId): bool
    {
        try {
            $key = $this->keyManagement->getAuthKey($keyId);
            Log::debug('Verifying signature', ['keyId' => $keyId]);
            return Crypto::verify($data->getString(), $key, $signature);
        } catch (\Exception $e) {
            Log::error('Verification failed', [
                'keyId' => $keyId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Verification failed: ' . $e->getMessage());
        }
    }
}