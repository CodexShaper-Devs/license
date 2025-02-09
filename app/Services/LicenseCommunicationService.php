<?php

namespace App\Services;

use ParagonIE\HiddenString\HiddenString;
use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use ParagonIE\Halite\Symmetric\AuthenticationKey;
use ParagonIE\Halite\KeyFactory;
use App\Exceptions\LicenseCommunicationException;

class LicenseCommunicationService
{
    private EncryptionKey $encryptionKey;
    private AuthenticationKey $authenticationKey;

    public function __construct(
        string $encryptionKeyMaterial,
        string $authenticationKeyMaterial
    ) {
        $this->encryptionKey = new EncryptionKey(
            new HiddenString($encryptionKeyMaterial)
        );
        $this->authenticationKey = new AuthenticationKey(
            new HiddenString($authenticationKeyMaterial)
        );
    }

    /**
     * Encrypts response data using ParagonIE's Halite library
     *
     * @param array $data The data to encrypt
     * @return string The encrypted data
     * @throws LicenseCommunicationException
     */
    public function encryptResponse(array $data): string
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            if ($json === false) {
                throw new \JsonException('Failed to encode response data');
            }

            return Crypto::encrypt(
                new HiddenString($json),
                $this->encryptionKey
            );
        } catch (\JsonException $e) {
            throw new LicenseCommunicationException(
                'Failed to encode response data: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        } catch (\Exception $e) {
            throw new LicenseCommunicationException(
                'Failed to encrypt response: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Decrypts received data using ParagonIE's Halite library
     *
     * @param string $encryptedData The encrypted data to decrypt
     * @return array The decrypted data
     * @throws LicenseCommunicationException
     */
    public function decryptRequest(string $encryptedData): array
    {
        try {
            $decrypted = Crypto::decrypt(
                $encryptedData,
                $this->encryptionKey
            );

            $data = json_decode($decrypted->getString(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new \JsonException('Decrypted data is not a valid JSON array');
            }

            return $data;
        } catch (\JsonException $e) {
            throw new LicenseCommunicationException(
                'Failed to decode decrypted data: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        } catch (\Exception $e) {
            throw new LicenseCommunicationException(
                'Failed to decrypt request: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Signs the data using the authentication key
     *
     * @param array $data The data to sign
     * @return string The signature
     * @throws LicenseCommunicationException
     */
    public function signData(array $data): string
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            if ($json === false) {
                throw new \JsonException('Failed to encode data for signing');
            }

            return Crypto::authenticate(
                new HiddenString($json),
                $this->authenticationKey
            );
        } catch (\Exception $e) {
            throw new LicenseCommunicationException(
                'Failed to sign data: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Verifies the signature of the data
     *
     * @param array $data The data to verify
     * @param string $signature The signature to verify against
     * @return bool Whether the signature is valid
     * @throws LicenseCommunicationException
     */
    public function verifySignature(array $data, string $signature): bool
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            if ($json === false) {
                throw new \JsonException('Failed to encode data for signature verification');
            }

            return Crypto::verify(
                new HiddenString($json),
                $this->authenticationKey,
                $signature
            );
        } catch (\Exception $e) {
            throw new LicenseCommunicationException(
                'Failed to verify signature: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}