<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class KeyManagementService
{
    public function generateKeys(string $keyId): array
    {
        // Generate encryption key
        $encryptionKey = sodium_crypto_secretbox_keygen();
        $encryptionPath = 'encryption_keys/' . $keyId . '.key';
        Storage::put($encryptionPath, base64_encode($encryptionKey));

        // Generate authentication key
        $authKey = sodium_crypto_auth_keygen();
        $authPath = 'auth_keys/' . $keyId . '.key';
        Storage::put($authPath, base64_encode($authKey));

        return [
            'encryption_key_id' => $keyId,
            'auth_key_id' => $keyId
        ];
    }

    public function ensureKeysExist(string $keyId): void
    {
        $encryptionPath = 'encryption_keys/' . $keyId . '.key';
        $authPath = 'auth_keys/' . $keyId . '.key';

        if (!Storage::exists($encryptionPath) || !Storage::exists($authPath)) {
            $this->generateKeys($keyId);
        }
    }
}