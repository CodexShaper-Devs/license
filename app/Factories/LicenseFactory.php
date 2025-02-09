<?php

namespace App\Factories;

use App\Models\License;
use App\Services\EncryptionService;
use App\Services\Marketplace\MarketplaceVerifier;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LicenseFactory
{
    private const TIMESTAMP = '2025-02-09 04:16:32';
    private const USER = 'maab16';

    public function __construct(
        private readonly array $verifiers,
        private readonly EncryptionService $encryption
    ) {}

    public function createLicense(array $data): License
    {
        $verifier = $this->getVerifier($data['source']);
        $purchaseData = $verifier->verifyPurchase($data['source_purchase_code']);

        $timestamp = Carbon::parse(self::TIMESTAMP);
        $currentUser = self::USER;

        // Generate license key and encryption keys
        $licenseKey = $this->generateLicenseKey($purchaseData);
        $encryptionKeyId = $this->generateEncryptionKeyId($purchaseData);
        $authKeyId = $this->generateAuthKeyId($purchaseData);

        // Prepare license data
        $licenseData = [
            'key' => $licenseKey,
            'source' => $data['source'],
            'source_purchase_code' => $data['source_purchase_code'],
            'source_data' => $purchaseData,
            'type' => $data['type'],
            'status' => 'active',
            'seats' => $data['seats'] ?? 1,
            'encryption_key_id' => $encryptionKeyId,
            'auth_key_id' => $authKeyId,
            'features' => $data['features'] ?? [],
            'restrictions' => $data['restrictions'] ?? [],
            'metadata' => $data['metadata'] ?? [],
            'settings' => $data['settings'] ?? [],
            'created_at' => $timestamp,
            'created_by' => $currentUser,
            'updated_at' => $timestamp
        ];

        // Handle validity period
        if ($data['type'] === License::TYPE_SUBSCRIPTION) {
            $licenseData['valid_from'] = $timestamp;
            $licenseData['valid_until'] = $data['valid_until'] ?? null;
        }

        // Create signature data
        $signatureData = $this->createSignatureData($licenseData, $purchaseData);
        
        // Encrypt and sign
        $encryptedData = $this->encryption->encrypt(
            $signatureData,
            $encryptionKeyId
        );

        $signature = $this->encryption->sign(
            $encryptedData,
            $authKeyId
        );

        $licenseData['signature'] = $signature;
        $licenseData['metadata']['_signature'] = [
            'data' => $signatureData,
            'encrypted' => $encryptedData
        ];

        return License::create($licenseData);
    }

    public function getVerifier(string $source): MarketplaceVerifier
    {
        if (!isset($this->verifiers[$source])) {
            throw new \InvalidArgumentException("Unsupported marketplace source: {$source}");
        }

        return $this->verifiers[$source];
    }

    private function generateLicenseKey(array $purchaseData): string
    {
        $entropy = hash('sha256', json_encode([
            'purchase_code' => $purchaseData['purchase_code'],
            'timestamp' => self::TIMESTAMP,
            'random' => Str::random(16)
        ]));

        return sprintf(
            'LICENSE-%s-%s-%s',
            date('Y'),
            strtoupper(substr($entropy, 0, 4)),
            strtoupper(substr($entropy, 4, 4))
        );
    }

    private function createSignatureData(array $licenseData, array $purchaseData): array
    {
        return [
            'key' => $licenseData['key'],
            'source' => $licenseData['source'],
            'source_purchase_code' => $licenseData['source_purchase_code'],
            'purchase_data' => [
                'code' => $purchaseData['purchase_code'],
                'buyer' => $purchaseData['buyer'] ?? null,
                'sold_at' => $purchaseData['sold_at'] ?? null,
            ],
            'type' => $licenseData['type'],
            'features' => $licenseData['features'],
            'restrictions' => $licenseData['restrictions'],
            'created_at' => self::TIMESTAMP,
            'created_by' => self::USER,
            'fingerprint' => $this->generateFingerprint($licenseData, $purchaseData)
        ];
    }

    private function generateEncryptionKeyId(array $purchaseData): string
    {
        $entropy = hash('sha256', json_encode([
            'purchase_code' => $purchaseData['purchase_code'],
            'timestamp' => self::TIMESTAMP,
            'type' => 'encryption',
            'random' => Str::random(16)
        ]));

        return substr($entropy, 0, 32);
    }

    private function generateAuthKeyId(array $purchaseData): string
    {
        $entropy = hash('sha256', json_encode([
            'purchase_code' => $purchaseData['purchase_code'],
            'timestamp' => self::TIMESTAMP,
            'type' => 'auth',
            'random' => Str::random(16)
        ]));

        return substr($entropy, 0, 32);
    }

    private function generateFingerprint(array $licenseData, array $purchaseData): string
    {
        return hash('sha256', json_encode([
            'key' => $licenseData['key'],
            'source' => $licenseData['source'],
            'purchase_code' => $purchaseData['purchase_code'],
            'timestamp' => self::TIMESTAMP,
            'features' => $licenseData['features'],
            'restrictions' => $licenseData['restrictions']
        ]));
    }
}