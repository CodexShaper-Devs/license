<?php

namespace App\Services;

use App\Models\License;
use App\Repositories\LicenseRepository;
use App\Exceptions\LicenseValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use ParagonIE\HiddenString\HiddenString;
use RuntimeException;

class LicenseService
{
    private const TIMESTAMP = '2025-02-09 09:15:12';
    private const USER = 'maab16';

    public function __construct(
        private readonly LicenseRepository $repository,
        private readonly EncryptionService $encryption,
        private readonly KeyManagementService $keyManagement
    ) {}

    public function createLicense(array $data): License
    {
        try {
            Log::info('Starting license creation');

            // Generate key ID and UUID
            $keyId = Str::random(32);
            $licenseId = (string) Str::uuid();

            // Generate encryption and authentication keys first
            Log::debug('Generating keys', ['keyId' => $keyId]);
            $keys = $this->keyManagement->generateKeys($keyId);

            // Create the license data
            $licenseData = array_merge($data, [
                'id' => $licenseId,
                'uuid' => Str::uuid(),
                'encryption_key_id' => $keyId,
                'auth_key_id' => $keyId,
                'created_by' => self::USER,
                'updated_by' => self::USER
            ]);

            // Convert license data to JSON
            $licenseContent = json_encode($licenseData);
            if ($licenseContent === false) {
                throw new \RuntimeException('Failed to encode license data');
            }

            // Create hidden string for content
            $hiddenContent = new HiddenString($licenseContent);

            // Encrypt the license content
            $encryptedContent = $this->encryption->encrypt($hiddenContent, $keyId);

            // Create a new HiddenString for signing
            $signatureContent = new HiddenString($licenseContent);
            $signature = $this->encryption->sign($signatureContent, $keyId);

            // Prepare final license data
            $finalLicenseData = array_merge($licenseData, [
                'key' => $encryptedContent,
                'signature' => $signature
            ]);

            // Create and save the license
            $license = License::create($finalLicenseData);
            Log::info('License created successfully', [
                'licenseId' => $license->id,
                'keyId' => $keyId
            ]);

            return $license;

        } catch (\Exception $e) {
            Log::error('License creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException('Failed to create license: ' . $e->getMessage());
        }
    }

    public function verifyLicense(License $license): bool
    {
        try {
            // Decrypt the license key
            $decrypted = $this->encryption->decrypt(
                $license->key,
                $license->encryption_key_id
            );

            // Create a new HiddenString for verification
            $verificationContent = new HiddenString($decrypted->getString());

            // Verify the signature
            return $this->encryption->verify(
                $verificationContent,
                $license->signature,
                $license->auth_key_id
            );

        } catch (\Exception $e) {
            Log::error('License verification failed', [
                'licenseId' => $license->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getLicenseContent(License $license): array
    {
        try {
            // Decrypt the license key
            $decrypted = $this->encryption->decrypt(
                $license->key,
                $license->encryption_key_id
            );

            // Create a new HiddenString for verification
            $verificationContent = new HiddenString($decrypted->getString());

            // Verify the signature
            if (!$this->encryption->verify(
                $verificationContent,
                $license->signature,
                $license->auth_key_id
            )) {
                throw new \RuntimeException('Invalid license signature');
            }

            // Decode the license content
            $content = json_decode($decrypted->getString(), true);
            if (!is_array($content)) {
                throw new \RuntimeException('Invalid license content format');
            }

            return $content;

        } catch (\Exception $e) {
            Log::error('Failed to get license content', [
                'licenseId' => $license->id,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to get license content: ' . $e->getMessage());
        }
    }

    public function validateLicense(string $licenseKey, array $validationData = []): array
    {
        $license = $this->repository->findByKey($licenseKey);

        if (!$license) {
            throw new LicenseValidationException('License not found');
        }

        if (!$this->repository->validateSignature($license)) {
            throw new LicenseValidationException('Invalid license signature');
        }

        $this->validateLicenseStatus($license);
        $this->validateLicenseExpiry($license);
        $this->validateLicenseRestrictions($license, $validationData);

        $this->repository->logLicenseEvent($license, 'validated', [
            'validation_data' => $validationData,
            'result' => 'success'
        ]);

        return [
            'valid' => true,
            'type' => $license->type,
            'source' => $license->source,
            'features' => $license->features,
            'expires_at' => $license->valid_until?->toIso8601String(),
            'validated_at' => self::TIMESTAMP
        ];
    }

    public function activateLicense(string $licenseKey, array $activationData): array
    {
        return DB::transaction(function () use ($licenseKey, $activationData) {
            $license = $this->repository->findByKey($licenseKey);

            if (!$license) {
                throw new LicenseValidationException('License not found');
            }

            if (!$this->repository->validateSignature($license)) {
                throw new LicenseValidationException('Invalid license signature');
            }

            $this->validateLicenseStatus($license);
            $this->validateLicenseExpiry($license);

            // Update activation data
            $metadata = $license->metadata ?? [];
            $metadata['activations'] = array_merge($metadata['activations'] ?? [], [
                [
                    'id' => Str::uuid(),
                    'timestamp' => self::TIMESTAMP,
                    'data' => $activationData,
                    'created_by' => self::USER
                ]
            ]);

            $this->repository->update($license, ['metadata' => $metadata]);

            $this->repository->logLicenseEvent($license, 'activated', [
                'activation_data' => $activationData
            ]);

            return [
                'status' => 'activated',
                'activation_id' => end($metadata['activations'])['id'],
                'timestamp' => self::TIMESTAMP
            ];
        });
    }

    public function deactivateLicense(string $licenseKey, string $activationId): array
    {
        return DB::transaction(function () use ($licenseKey, $activationId) {
            $license = $this->repository->findByKey($licenseKey);

            if (!$license) {
                throw new LicenseValidationException('License not found');
            }

            $metadata = $license->metadata ?? [];
            $activations = $metadata['activations'] ?? [];

            $found = false;
            foreach ($activations as $key => $activation) {
                if ($activation['id'] === $activationId) {
                    unset($activations[$key]);
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                throw new LicenseValidationException('Activation not found');
            }

            $metadata['activations'] = array_values($activations);
            $this->repository->update($license, ['metadata' => $metadata]);

            $this->repository->logLicenseEvent($license, 'deactivated', [
                'activation_id' => $activationId
            ]);

            return [
                'status' => 'deactivated',
                'timestamp' => self::TIMESTAMP
            ];
        });
    }

    private function validateSourceData(array &$data): void
    {
        if ($data['source'] === 'envato' && empty($data['source_purchase_code'])) {
            throw new LicenseValidationException('Envato purchase code is required');
        }

        if ($data['type'] === 'onetime') {
            $data['valid_until'] = Carbon::parse(self::TIMESTAMP)->addYears(100);
        }
    }

    private function validateLicenseStatus(License $license): void
    {
        if ($license->status !== 'active') {
            throw new LicenseValidationException("License is {$license->status}");
        }
    }

    private function validateLicenseExpiry(License $license): void
    {
        if ($license->valid_until && $license->valid_until < Carbon::parse(self::TIMESTAMP)) {
            throw new LicenseValidationException('License has expired');
        }
    }

    private function validateLicenseRestrictions(License $license, array $validationData): void
    {
        $restrictions = $license->restrictions ?? [];

        if (isset($restrictions['domain']) && 
            isset($validationData['domain']) && 
            $restrictions['domain'] !== $validationData['domain']) {
            throw new LicenseValidationException('Invalid domain');
        }

        if (isset($restrictions['environment']) && 
            isset($validationData['environment']) && 
            $restrictions['environment'] !== $validationData['environment']) {
            throw new LicenseValidationException('Invalid environment');
        }
    }

    private function prepareSignatureData(License $license, Carbon $timestamp): array
    {
        $data = [
            'key' => $license->key,
            'source' => $license->source,
            'type' => $license->type,
            'product_id' => $license->product_id,
            'features' => $license->features,
            'valid_from' => $license->valid_from->toIso8601String(),
            'valid_until' => $license->valid_until?->toIso8601String(),
            'created_at' => $timestamp->toIso8601String(),
            'created_by' => self::USER
        ];

        if ($license->source === 'envato') {
            $data['purchase_code'] = $license->source_purchase_code;
            $data['source_data'] = $license->source_data;
        }

        return $data;
    }

    /**
     * Find a license by Envato purchase code
     *
     * @param string $purchaseCode
     * @return License
     * @throws LicenseValidationException
     */
    public function findByPurchaseCode(string $purchaseCode): License
    {
        $license = $this->repository->findByPurchaseCode($purchaseCode);

        if (!$license) {
            throw new LicenseValidationException("No license found for purchase code: {$purchaseCode}");
        }

        return $license;
    }

    public function generateLicenseKey(array $data): string 
    {
        // Create base identifier
        $baseId = hex2bin(str_replace('-', '', Str::uuid()));
        
        // Compress the data
        $compressed = gzcompress(json_encode($data), 9);
        
        // Combine and encode
        $combined = $baseId . $compressed;
        
        // Return URL-safe base64 encoding
        return rtrim(strtr(base64_encode($combined), '+/', '-_'), '=');
    }

    public function decodeLicenseKey(string $key): array
    {
        try {
            // Decode base64
            $decoded = base64_decode(strtr($key, '-_', '+/'));
            
            // Extract UUID and compressed data
            $uuid = bin2hex(substr($decoded, 0, 16));
            $compressed = substr($decoded, 16);
            
            // Decompress data
            $data = gzuncompress($compressed);
            
            return json_decode($data, true);
        } catch (\Exception $e) {
            throw new RuntimeException('Invalid license key');
        }
    }

    private function validateDomain(string $domain, License $license): bool
    {
        return $license->domains()
            ->where('is_active', true)
            ->get()
            ->contains(function ($licenseDomain) use ($domain) {
                return $licenseDomain->isValidDomain($domain);
            });
    }
}