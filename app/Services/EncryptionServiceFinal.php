<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class EncryptionServiceFinal
{
    private const ENCRYPTION_KEY_DIRECTORY = 'encryption_keys';
    private const AUTH_KEY_DIRECTORY = 'auth_keys';

    public function encrypt(array $data, string $keyId): string
    {
        $key = $this->getKey($keyId, self::ENCRYPTION_KEY_DIRECTORY);
        $jsonData = json_encode($data);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        
        $encrypted = sodium_crypto_secretbox(
            $jsonData,
            $nonce,
            $key
        );
        
        return base64_encode($nonce . $encrypted);
    }

    public function decrypt(string $data, string $keyId): array
    {
        $key = $this->getKey($keyId, self::ENCRYPTION_KEY_DIRECTORY);
        $decoded = base64_decode($data);
        
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $encrypted = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        
        $decrypted = sodium_crypto_secretbox_open(
            $encrypted,
            $nonce,
            $key
        );
        
        if ($decrypted === false) {
            throw new \Exception('Decryption failed');
        }
        
        return json_decode($decrypted, true);
    }

    public function sign(string $data, string $keyId): string
    {
        $key = $this->getKey($keyId, self::AUTH_KEY_DIRECTORY);
        $signature = sodium_crypto_auth($data, $key);
        return base64_encode($signature);
    }

    public function verify(string $data, string $signature, string $keyId): bool
    {
        try {
            $key = $this->getKey($keyId, self::AUTH_KEY_DIRECTORY);
            $decodedSignature = base64_decode($signature);
            
            return sodium_crypto_auth_verify(
                $decodedSignature,
                $data,
                $key
            );
        } catch (\Exception $e) {
            Log::error('Verification failed: ' . $e->getMessage());
            return false;
        }
    }

    private function getKey(string $keyId, string $directory): string
    {
        $path = $directory . '/' . $keyId . '.key';
        
        if (!Storage::exists($path)) {
            throw new \Exception("Key not found: {$path}");
        }
        
        return base64_decode(Storage::get($path));
    }
}