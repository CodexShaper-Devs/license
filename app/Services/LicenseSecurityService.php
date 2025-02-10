<?php

namespace App\Services;

use ParagonIE\HiddenString\HiddenString;
use Illuminate\Support\Str;
use App\Models\License;
use App\Repositories\LicenseRepository;
use App\Services\Storage\KeyStorageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LicenseSecurityService
{
    private const CURRENT_TIME = '2025-02-10 06:00:09';
    private const CURRENT_USER = 'maab16';
    
    private ?string $currentKeyId = null;

    public function __construct(
        private readonly KeyManagementService $keyManagement,
        private readonly EncryptionService $encryption,
        private readonly KeyStorageService $keyStorage,
        private readonly LicenseRepository $repository
    ) {}

    public function verifyLicenseSecurity(License $license, array $validationData = []): bool
    {
        try {
            $keyId = $license->auth_key_id;
            $this->currentKeyId = $keyId;

            Log::info('Starting license security verification', [
                'keyId' => $keyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            // If keys don't exist, regenerate them using the original key ID
            if (!$this->hasKeys($keyId)) {
                Log::info('Regenerating missing keys', [
                    'keyId' => $keyId,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                $this->keyManagement->generateKeys($keyId);
                
                // Verify keys were generated successfully
                if (!$this->hasKeys($keyId)) {
                    throw new Exception("Failed to regenerate keys for ID: {$keyId}");
                }
            }

            $encryptedData = new HiddenString($license->key);
            $signature = new HiddenString($license->signature);

            // Verify the signature using the existing or regenerated keys
            $isValid = $this->encryption->verify(
                $encryptedData,
                $signature,
                $keyId
            );

            Log::info('License security verification completed', [
                'keyId' => $keyId,
                'isValid' => $isValid,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return $isValid;

        } catch (Exception $e) {
            Log::error('License security verification failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER,
                'keyId' => $this->currentKeyId
            ]);
            return false;
        }
    }

    public function hasKeys(string $keyId): bool
    {
        try {
            $encryptionKeyExists = $this->keyStorage->exists(
                $this->getEncryptionKeyPath($keyId)
            );
            
            $authKeyExists = $this->keyStorage->exists(
                $this->getAuthKeyPath($keyId)
            );
            
            $hasKeys = $encryptionKeyExists && $authKeyExists;

            Log::info('Key verification status', [
                'keyId' => $keyId,
                'hasKeys' => $hasKeys,
                'encryptionKeyExists' => $encryptionKeyExists,
                'authKeyExists' => $authKeyExists,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return $hasKeys;
        } catch (Exception $e) {
            Log::error('Key verification failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER,
                'keyId' => $keyId
            ]);
            return false;
        }
    }

    private function getEncryptionKeyPath(string $keyId): string
    {
        return "keys/encryption/{$keyId}.key";
    }

    private function getAuthKeyPath(string $keyId): string
    {
        return "keys/auth/{$keyId}.key";
    }

    public function generateLicenseSecurityData(array $data): array
    {
        // Use license key to generate consistent key ID
        $this->currentKeyId = $this->getKeyIdFromLicense($data['license_key']);
        
        try {
            Log::info('Starting license security data generation', [
                'keyId' => $this->currentKeyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            // Generate keys if they don't exist
            if (!$this->hasKeys($this->currentKeyId)) {
                $this->keyManagement->generateKeys($this->currentKeyId);
            }

            $this->verifyKeysExist($this->currentKeyId);

            $licenseData = array_merge($data, [
                'created_at' => self::CURRENT_TIME,
                'created_by' => self::CURRENT_USER,
                'security_version' => 'v1'
            ]);

            // Use the same keyId for encryption
            $encryptedContent = $this->encryptLicenseContent($licenseData, $this->currentKeyId);

            // Use the same keyId for signing
            $signature = $this->encryption->sign(
                new HiddenString($encryptedContent),
                $this->currentKeyId
            );

            Log::info('License security data generated successfully', [
                'keyId' => $this->currentKeyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return [
                'encrypted_content' => $encryptedContent,
                'signature' => $signature->getString(),
                'key_id' => $this->currentKeyId,
                'auth_key_id' => $this->currentKeyId,
                'security_metadata' => [
                    'algorithm' => 'XSALSA20_POLY1305',
                    'key_version' => 'v1',
                    'hardware_verification' => false,
                    'timestamp' => self::CURRENT_TIME,
                    'created_by' => self::CURRENT_USER
                ]
            ];

        } catch (Exception $e) {
            Log::error('License security data generation failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER,
                'keyId' => $this->currentKeyId
            ]);
            throw $e;
        }
    }

    private function getKeyIdFromLicense(string $licenseKey): string
    {
        // Always use the same algorithm for key ID generation
        return md5($licenseKey . 'v1');
    }

    private function verifyKeysExist(string $keyId): void
    {
        if (!$this->hasKeys($keyId)) {
            throw new RuntimeException("Required keys not found for ID: {$keyId}");
        }
    }

    public function getOrGenerateKeys(string $licenseKey): string
    {
        // Generate consistent key ID from license key
        $keyId = $this->getKeyIdFromLicense($licenseKey);

        // Check if keys exist
        if (!$this->hasKeys($keyId)) {
            // Generate new keys if they don't exist
            $this->keyManagement->generateKeys($keyId);
            
            Log::info('New keys generated', [
                'keyId' => $keyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
        }

        return $keyId;
    }

    public function activateLicense(string $licenseKey, array $activationData): array
    {
        try {
            // Get or generate keys using the license key
            $keyId = $this->getOrGenerateKeys($licenseKey);

            // Find license
            $license = $this->repository->findByKey($licenseKey);
            if (!$license) {
                throw new Exception('License not found or invalid');
            }

            // Verify the license security using the same keyId
            if (!$this->verifyLicenseSecurity($license, [
                'auth_key_id' => $keyId,
                'encryption_key_id' => $keyId,
                'timestamp' => self::CURRENT_TIME
            ])) {
                throw new Exception('Invalid license security verification');
            }

            // Create activation record
            $activation = $license->activations()->create([
                'id' => Str::uuid(),
                'license_id' => $license->id,
                'activation_token' => Str::random(32),
                'type' => $activationData['type'] ?? 'domain',
                'device_identifier' => substr($activationData['device_identifier'], 0, 255),
                'device_name' => substr($activationData['device_name'], 0, 255),
                'hardware_hash' => isset($activationData['hardware_info']) ? 
                    json_encode($activationData['hardware_info']) : null,
                'system_info' => isset($activationData['system_info']) ? 
                    json_encode($activationData['system_info']) : null,
                'ip_address' => substr(request()->ip(), 0, 45),
                'mac_address' => $activationData['hardware_info']['mac_address'] ?? null,
                'is_active' => true,
                'activated_at' => self::CURRENT_TIME,
                'created_by' => self::CURRENT_USER
            ]);

            return [
                'status' => 'activated',
                'activation_id' => $activation->id,
                'activation_token' => $activation->activation_token,
                'key_id' => $keyId,
                'next_check_in' => Carbon::parse(self::CURRENT_TIME)
                    ->addDays($license->product->check_in_interval_days)
                    ->toIso8601String()
            ];

        } catch (Exception $e) {
            Log::error('License activation failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER,
                'keyId' => $keyId ?? null
            ]);
            throw $e;
        }
    }

    private function encryptLicenseContent(array $content, string $keyId): string
    {
        try {
            $jsonContent = json_encode($content);
            if ($jsonContent === false) {
                throw new RuntimeException('Failed to encode license content');
            }

            $encrypted = $this->encryption->encrypt($content, $keyId);
            return $encrypted->getString();
        } catch (\Exception $e) {
            Log::error('Encryption failed', [
                'keyId' => $keyId,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME
            ]);
            throw $e;
        }
    }

    private function generateKeyId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function createSecureLicense(array $data): array
    {
        try {
            if (empty($data['license_key'])) {
                throw new RuntimeException('License key is required');
            }

            $keyId = $this->getKeyIdFromLicense($data['license_key']);
            
            Log::info('Starting secure license creation', [
                'keyId' => $keyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            // Generate keys if they don't exist
            if (!$this->hasKeys($keyId)) {
                $this->keyManagement->generateKeys($keyId);
            }

            // Verify keys exist
            $this->verifyKeysExist($keyId);

            // Add security metadata
            $licenseData = array_merge($data, [
                'created_at' => self::CURRENT_TIME,
                'created_by' => self::CURRENT_USER,
                'security_version' => 'v1'
            ]);

            // Encrypt the license data
            $encryptedData = $this->encryptLicenseContent($licenseData, $keyId);
            
            // Sign the encrypted data
            $signature = $this->encryption->sign(
                new HiddenString($encryptedData), 
                $keyId
            );

            Log::info('License secured successfully', [
                'keyId' => $keyId,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return [
                'key' => $encryptedData,
                'signature' => $signature->getString(),
                'encryption_key_id' => $keyId,
                'auth_key_id' => $keyId,
                'security_metadata' => [
                    'algorithm' => 'XSALSA20_POLY1305',
                    'timestamp' => self::CURRENT_TIME,
                    'created_by' => self::CURRENT_USER,
                    'key_version' => 'v1',
                    'hardware_verification' => false
                ]
            ];

        } catch (\Exception $e) {
            Log::error('License creation failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            throw $e;
        }
    }

    public function verifyLicense(HiddenString $encryptedData, HiddenString $signature, string $keyId): bool
    {
        try {
            return $this->encryption->verify($encryptedData, $signature, $keyId);
        } catch (\Exception $e) {
            Log::error('License verification failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            return false;
        }
    }

    public function generateHardwareFingerprint(array $hardwareInfo): string
    {
        try {
            // Sort hardware info to ensure consistent order
            ksort($hardwareInfo);

            // Extract critical hardware components
            $criticalComponents = $this->extractCriticalComponents($hardwareInfo);

            // Generate primary fingerprint from all hardware info
            $primaryFingerprint = hash('sha512', json_encode($hardwareInfo));

            // Generate secondary fingerprint from critical components
            $secondaryFingerprint = hash('sha512', json_encode($criticalComponents));

            // Combine fingerprints with additional entropy
            return hash('sha512', 
                $primaryFingerprint . 
                $secondaryFingerprint . 
                $this->generateEntropy($hardwareInfo)
            );
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to generate hardware fingerprint: ' . $e->getMessage());
        }
    }

    private function extractCriticalComponents(array $hardwareInfo): array
    {
        $criticalKeys = [
            'cpu_id',
            'motherboard_serial',
            'bios_serial',
            'disk_serial',
            'mac_address',
            'product_id',
            'system_uuid'
        ];

        $critical = [];
        foreach ($criticalKeys as $key) {
            if (isset($hardwareInfo[$key])) {
                $critical[$key] = $hardwareInfo[$key];
            }
        }

        if (count($critical) < 3) {
            throw new \RuntimeException('Insufficient hardware information provided');
        }

        return $critical;
    }

    private function generateEntropy(array $hardwareInfo): string
    {
        return hash('sha256', json_encode([
            'timestamp' => self::CURRENT_TIME,
            'component_count' => count($hardwareInfo),
            'random' => random_bytes(32),
            'unique_id' => Str::uuid()->toString()
        ]));
    }

    public function verifyHardwareFingerprint(string $storedFingerprint, array $currentHardware): bool
    {
        try {
            // Generate current fingerprint
            $currentFingerprint = $this->generateHardwareFingerprint($currentHardware);

            // Direct match
            if (hash_equals($currentFingerprint, $storedFingerprint)) {
                return true;
            }

            // Fallback to critical components only
            $criticalComponents = $this->extractCriticalComponents($currentHardware);
            $criticalFingerprint = hash('sha512', json_encode($criticalComponents));
            
            // Calculate similarity score for critical components
            return $this->calculateFingerprintSimilarity(
                $criticalFingerprint,
                $storedFingerprint
            ) >= 0.85; // 85% similarity threshold
        } catch (\Exception $e) {
            return false;
        }
    }

    private function calculateFingerprintSimilarity(string $fingerprint1, string $fingerprint2): float
    {
        $binary1 = hex2bin($fingerprint1);
        $binary2 = hex2bin($fingerprint2);

        if (!$binary1 || !$binary2 || strlen($binary1) !== strlen($binary2)) {
            return 0.0;
        }

        $matchingBytes = 0;
        $totalBytes = strlen($binary1);

        for ($i = 0; $i < $totalBytes; $i++) {
            $xor = ord($binary1[$i]) ^ ord($binary2[$i]);
            $matchingBytes += (8 - substr_count(decbin($xor), '1'));
        }

        return $matchingBytes / ($totalBytes * 8);
    }

    public function generateDomainValidationHash(string $domain, string $licenseKey): string
    {
        $data = json_encode([
            'domain' => strtolower($domain),
            'license_key' => $licenseKey,
            'timestamp' => self::CURRENT_TIME,
        ]);

        return $this->encryption->sign(new HiddenString($data), $this->generateKeyId());
    }

    public function verifyDomainValidation(string $domain, string $hash, string $licenseKey): bool
    {
        try {
            $expectedHash = $this->generateDomainValidationHash($domain, $licenseKey);
            return hash_equals($expectedHash, $hash);
        } catch (\Exception $e) {
            return false;
        }
    }
}