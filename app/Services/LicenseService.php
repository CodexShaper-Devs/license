<?php

namespace App\Services;

use App\Models\License;
use App\Repositories\LicenseRepository;
use Exception;
use App\Models\LicenseDomain;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use ParagonIE\HiddenString\HiddenString;
use RuntimeException;

class LicenseService
{
    private const TIMESTAMP = '2025-02-09 15:26:36';
    private const USER = 'maab16';

    public function __construct(
        private readonly LicenseRepository $repository,
        private readonly LicenseSecurityService $securityService,
        private readonly EnvatoService $envato,
        private readonly EncryptionService $encryption,
        private readonly LicenseActivationService $activationService,
        private readonly LicenseDeactivationService $deactivationService,
    ) {}

    public function createLicense(array $data): License
    {
        return DB::transaction(function () use ($data) {
            try {
                // Generate a unique license key if not provided
                $licenseKey = $data['license_key'] ?? $this->generateLicenseKey();

                Log::info('Starting license creation', [
                    'license_key' => $licenseKey,
                    'timestamp' => self::TIMESTAMP,
                    'user' => self::USER
                ]);

                // Generate security data with the license key
                $securityData = $this->securityService->createSecureLicense([
                    'license_key' => $licenseKey,
                    'product_id' => $data['product_id'],
                    'plan_id' => $data['plan_id'],
                    'user_id' => $data['user_id'] ?? null,
                    'type' => $data['type'] ?? 'subscription',
                    'features' => $data['features'] ?? [],
                    'restrictions' => $data['restrictions'] ?? [],
                    'valid_from' => self::TIMESTAMP,
                    'valid_until' => $data['valid_until'] ?? null,
                    'created_by' => self::USER
                ]);

                // Create the license record
                $license = License::create([
                    'uuid' => (string) Str::uuid(),
                    'key' => $securityData['key'],
                    'signature' => $securityData['signature'],
                    'encryption_key_id' => $securityData['encryption_key_id'],
                    'auth_key_id' => $securityData['auth_key_id'],
                    'security_metadata' => json_encode($securityData['security_metadata']),
                    'product_id' => $data['product_id'],
                    'plan_id' => $data['plan_id'],
                    'user_id' => $data['user_id'] ?? null,
                    'type' => $data['type'] ?? 'subscription',
                    'purchased_seats' => $data['purchased_seats'] ?? 1,
                    'activated_seats' => 0,
                    'source' => $data['source'] ?? 'custom',
                    'source_purchase_code' => $data['source_purchase_code'] ?? null,
                    'features' => json_encode($data['features'] ?? []),
                    'restrictions' => json_encode($data['restrictions'] ?? []),
                    'valid_from' => self::TIMESTAMP,
                    'valid_until' => $data['valid_until'] ?? null,
                    'trial_ends_at' => $data['trial_ends_at'] ?? null,
                    'status' => $data['status'] ?? 'pending',
                    'created_by' => self::USER,
                    'created_at' => self::TIMESTAMP,
                    'updated_at' => self::TIMESTAMP
                ]);

                Log::info('License created successfully', [
                    'license_id' => $license->id,
                    'license_key' => $licenseKey,
                    'timestamp' => self::TIMESTAMP,
                    'user' => self::USER
                ]);

                return $license;

            } catch (\Exception $e) {
                Log::error('License creation failed', [
                    'error' => $e->getMessage(),
                    'timestamp' => self::TIMESTAMP,
                    'user' => self::USER
                ]);
                throw new RuntimeException('Failed to create license: ' . $e->getMessage());
            }
        });
    }

    private function generateLicenseKey(): string
    {
        return sprintf(
            '%s_%s',
            strtoupper(Str::random(6)),
            Str::random(32)
        );
    }

    public function renewLicense(License $license, array $data): License
    {
        return DB::transaction(function () use ($license, $data) {
            // Validate renewal eligibility
            if (!$this->canRenewLicense($license)) {
                throw new Exception('License is not eligible for renewal');
            }

            $renewalPeriod = $data['period'] ?? 'yearly';
            $validUntil = match($renewalPeriod) {
                'yearly' => Carbon::parse(self::TIMESTAMP)->addYear(),
                'lifetime' => null,
                default => throw new Exception('Invalid renewal period')
            };

            // Update license
            $updateData = [
                'valid_until' => $validUntil,
                'status' => License::STATUS_ACTIVE,
                'updated_by' => self::USER,
                'renewal_reminder_sent_at' => null
            ];

            if (isset($data['seats'])) {
                $updateData['purchased_seats'] = $data['seats'];
            }

            $license = $this->repository->update($license, $updateData);

            // Log renewal
            $this->repository->logLicenseEvent($license, 'renewed', [
                'period' => $renewalPeriod,
                'seats' => $data['seats'] ?? $license->purchased_seats,
                'previous_expiry' => $license->valid_until?->toIso8601String(),
                'new_expiry' => $validUntil?->toIso8601String()
            ]);

            return $license;
        });
    }

    public function activateLicense(string $licenseKey, array $activationData): array
    {
        return DB::transaction(function () use ($licenseKey, $activationData) {
            try {
                // Find license
                $license = $this->repository->findByKey($licenseKey);
                if (!$license) {
                    throw new RuntimeException('License not found');
                }

                // Verify license security first (don't modify this part)
                if (!$this->securityService->verifyLicenseSecurity($license, $activationData)) {
                    throw new RuntimeException('Invalid license security verification');
                }

                // Use the new activation service for domain and seat validation
                return $this->activationService->activateLicense($license, $activationData);

            } catch (\Exception $e) {
                Log::error('License activation failed', [
                    'error' => $e->getMessage(),
                    'timestamp' => self::TIMESTAMP,
                    'user' => self::USER
                ]);
                throw $e;
            }
        });
    }

    public function deactivateLicense(string $licenseKey, string $activationToken, ?string $reason = null): array
    {
        return DB::transaction(function () use ($licenseKey, $activationToken, $reason) {
            try {
                // Find license
                $license = $this->repository->findByKey($licenseKey);
                if (!$license) {
                    throw new RuntimeException('License not found');
                }

                return $this->deactivationService->deactivateLicense($license, $activationToken, $reason);

            } catch (\Exception $e) {
                Log::error('License deactivation failed', [
                    'error' => $e->getMessage(),
                    'timestamp' => self::TIMESTAMP,
                    'user' => self::USER
                ]);
                throw $e;
            }
        });
    }

    public function deactivateByDomain(
        string $licenseKey, 
        string $domain, 
        string $activationToken
    ): array {
        return DB::transaction(function () use ($licenseKey, $domain, $activationToken) {
            try {
                // Find license
                $license = $this->repository->findByKey($licenseKey);
                if (!$license) {
                    throw new RuntimeException('License not found');
                }
    
                return $this->deactivationService->deactivateByDomain(
                    $license, 
                    $domain, 
                    $activationToken
                );
    
            } catch (\Exception $e) {
                Log::error('Domain-based deactivation failed', [
                    'error' => $e->getMessage(),
                    'timestamp' => self::TIMESTAMP,
                    'user' => self::USER
                ]);
                throw $e;
            }
        });
    }

    public function deactivateEntireLicense(string $licenseKey, string $reason = null): array
    {
        return DB::transaction(function () use ($licenseKey, $reason) {
            try {
                // Find license
                $license = $this->repository->findByKey($licenseKey);
                if (!$license) {
                    throw new RuntimeException('License not found');
                }

                return $this->deactivationService->deactivateEntireLicense(
                    $license,
                    $reason ?? 'Complete license deactivation requested'
                );

            } catch (\Exception $e) {
                Log::error('Complete license deactivation failed', [
                    'error' => $e->getMessage(),
                    'timestamp' => self::TIMESTAMP,
                    'user' => self::USER
                ]);
                throw $e;
            }
        });
    }

    public function bulkDeactivate(string $licenseKey, array $filters = []): array
    {
        $license = $this->repository->findByKey($licenseKey);
        if (!$license) {
            throw new RuntimeException('License not found');
        }

        return $this->deactivationService->bulkDeactivate($license, $filters);
    }

    private function validateLicenseType(array $data): void
    {
        if (!isset($data['type'])) {
            throw new Exception('License type is required');
        }

        $validTypes = [
            License::TYPE_SUBSCRIPTION,
            License::TYPE_LIFETIME,
            License::TYPE_TRIAL
        ];

        if (!in_array($data['type'], $validTypes)) {
            throw new Exception('Invalid license type');
        }

        // Validate type-specific requirements
        switch ($data['type']) {
            case License::TYPE_SUBSCRIPTION:
                if (!isset($data['purchased_seats'])) {
                    throw new Exception('Number of seats is required for subscription licenses');
                }
                break;
            case License::TYPE_TRIAL:
                if (!isset($data['trial_days'])) {
                    throw new Exception('Trial period is required for trial licenses');
                }
                break;
            case License::TYPE_LIFETIME:
                // Validate Envato or custom lifetime specific requirements
                if ($data['source'] === 'envato' && empty($data['source_purchase_code'])) {
                    throw new Exception('Purchase code is required for Envato licenses');
                }
                break;
        }
    }

    private function canRenewLicense(License $license): bool
    {
        // Can't renew lifetime licenses
        if ($license->type === License::TYPE_LIFETIME) {
            return false;
        }

        // Can't renew suspended licenses
        if ($license->status === License::STATUS_SUSPENDED) {
            return false;
        }

        // Can't renew cancelled licenses
        if ($license->status === License::STATUS_CANCELLED) {
            return false;
        }

        // Trial licenses can only be converted to regular licenses, not renewed
        if ($license->type === License::TYPE_TRIAL) {
            return false;
        }

        // Check if the license is within the renewal window
        if ($license->valid_until) {
            $renewalWindowStart = $license->valid_until->copy()->subDays(30);
            $now = Carbon::parse('2025-02-09 15:31:48');
            
            // Can only renew within 30 days of expiration or up to 7 days after expiration
            if ($now < $renewalWindowStart || $now > $license->valid_until->addDays(7)) {
                return false;
            }
        }

        return true;
    }

    private function validateSourceData(array &$data): void
    {
        if (!isset($data['source'])) {
            $data['source'] = 'custom';
        }

        $validSources = ['custom', 'envato', 'other'];
        if (!in_array($data['source'], $validSources)) {
            throw new Exception('Invalid license source');
        }

        if ($data['source'] === 'envato') {
            if (empty($data['source_purchase_code'])) {
                throw new Exception('Envato purchase code is required');
            }
        }

        // Handle one-time license expiration
        if ($data['type'] === 'lifetime') {
            $data['valid_until'] = null;
        }
    }

    private function validateLicenseForActivation(License $license, array $activationData): void
    {
        if (!$this->securityService->verifyLicenseSecurity($license, $activationData)) {
            throw new Exception('Invalid license security verification');
        }

        if ($license->status === License::STATUS_SUSPENDED) {
            throw new Exception('License is suspended');
        }

        if ($license->status === License::STATUS_CANCELLED) {
            throw new Exception('License is cancelled');
        }

        if ($license->status === License::STATUS_EXPIRED) {
            throw new Exception('License has expired');
        }

        if ($license->valid_until && $license->valid_until < Carbon::parse('2025-02-09 15:31:48')) {
            throw new Exception('License has expired');
        }

        // Validate hardware if required
        if ($license->hardware_verification_enabled && !isset($activationData['hardware_info'])) {
            throw new Exception('Hardware information is required for activation');
        }
    }

    private function validateDomain(string $domain, License $license): bool
    {
        // Check if domain is active for this license
        $activeDomain = $license->domains()
            ->where('domain', $domain)
            ->where('is_active', true)
            ->first();

        if (!$activeDomain) {
            return false;
        }

        // Check if domain is allowed based on license plan
        if ($license->plan) {
            // Check for local domain exception
            if ($this->isLocalDomain($domain) && $license->plan->allow_local_domains) {
                return true;
            }

            // Check domain pattern restrictions
            if (!empty($license->plan->allowed_domain_patterns)) {
                $isAllowed = false;
                foreach ($license->plan->allowed_domain_patterns as $pattern) {
                    if (fnmatch($pattern, $domain)) {
                        $isAllowed = true;
                        break;
                    }
                }
                if (!$isAllowed) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isLocalDomain(string $domain): bool
    {
        $localTlds = ['.test', '.local', '.localhost', '.example', '.invalid'];
        foreach ($localTlds as $tld) {
            if (str_ends_with($domain, $tld)) {
                return true;
            }
        }
        return $domain === 'localhost';
    }

    private function calculateValidUntil(array $data): ?Carbon
    {
        if ($data['type'] === License::TYPE_LIFETIME) {
            return null;
        }

        $now = Carbon::parse('2025-02-09 15:31:48');

        if ($data['type'] === License::TYPE_TRIAL) {
            return $now->copy()->addDays($data['trial_days']);
        }

        // Default to yearly subscription
        return $now->copy()->addYear();
    }

    private function validateLicenseStatus(License $license): void
    {
        if ($license->status !== License::STATUS_ACTIVE) {
            throw new Exception("License is {$license->status}");
        }
    }

    private function validateLicenseExpiry(License $license): void
    {
        if ($license->valid_until && $license->valid_until < Carbon::parse('2025-02-09 15:31:48')) {
            // Check for grace period
            if ($license->grace_period_ends_at && 
                $license->grace_period_ends_at > Carbon::parse('2025-02-09 15:31:48')) {
                return;
            }
            throw new Exception('License has expired');
        }
    }

    private function addDomain(License $license, string $domain, bool $isPrimary = false): LicenseDomain
    {
        // Validate domain format
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new Exception('Invalid domain format');
        }

        // Check if domain is already active
        if ($license->domains()->where('domain', $domain)->where('is_active', true)->exists()) {
            throw new Exception('Domain is already activated');
        }

        // Check available seats
        if (!$license->hasAvailableSeats()) {
            throw new Exception('No available seats for domain activation');
        }

        // Create domain record
        return $license->domains()->create([
            'domain' => $domain,
            'is_primary' => $isPrimary,
            'is_active' => true,
            'validation_token' => $this->securityService->generateDomainValidationHash($domain, $license->key),
            'validated_at' => Carbon::parse('2025-02-09 15:31:48'),
            'created_by' => 'maab16'
        ]);
    }

    public function validateLicense(string $licenseKey, array $validationData = []): array
    {
        $license = $this->repository->findByKey($licenseKey);

        if (!$license) {
            throw new Exception('License not found');
        }

        // Verify security
        if (!$this->securityService->verifyLicenseSecurity($license, $validationData)) {
            throw new Exception('Invalid license security verification');
        }

        // Validate domain if provided
        if (isset($validationData['domain'])) {
            if (!$this->validateDomain($validationData['domain'], $license)) {
                throw new Exception('Invalid domain');
            }
        }

        // Validate status and expiry
        $this->validateLicenseStatus($license);
        $this->validateLicenseExpiry($license);

        // Log validation
        $this->repository->logLicenseEvent($license, 'validated', [
            'event_data' => $validationData,
            'result' => 'success'
        ]);

        return [
            'valid' => true,
            'type' => $license->type,
            'source' => $license->source,
            'features' => $license->features,
            'expires_at' => $license->valid_until?->toIso8601String(),
            'next_check_in' => $license->next_check_in?->toIso8601String(),
            'validated_at' => self::TIMESTAMP
        ];
    }

    public function checkIn(string $licenseKey, array $checkInData): array
    {
        return DB::transaction(function () use ($licenseKey, $checkInData) {
            $license = $this->repository->findByKey($licenseKey);

            if (!$license) {
                throw new Exception('License not found');
            }

            // Validate check-in data
            if (!isset($checkInData['domain'])) {
                throw new Exception('Domain is required for check-in');
            }

            // Find active domain and activation
            $domain = $license->domains()
                ->where('domain', $checkInData['domain'])
                ->where('is_active', true)
                ->first();

            if (!$domain) {
                throw new Exception('Domain not found or inactive');
            }

            $activation = $license->activations()
                ->where('domain_id', $domain->id)
                ->where('is_active', true)
                ->first();

            if (!$activation) {
                throw new Exception('No active activation found for domain');
            }

            // Update check-in times
            $nextCheckIn = Carbon::parse(self::TIMESTAMP)
                ->addDays($license->product->check_in_interval_days);

            $activation->update([
                'last_check_in' => self::TIMESTAMP,
                'next_check_in' => $nextCheckIn,
                'failed_checks' => 0
            ]);

            // Log check-in
            $this->repository->logLicenseEvent($license, 'checked_in', [
                'domain' => $checkInData['domain'],
                'check_in_time' => self::TIMESTAMP,
                'next_check_in' => $nextCheckIn->toIso8601String()
            ]);

            return [
                'status' => 'success',
                'next_check_in' => $nextCheckIn->toIso8601String(),
                'checked_in_at' => self::TIMESTAMP
            ];
        });
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

    private function validateLicenseRestrictions(License $license, array $validationData): void
    {
        $restrictions = $license->restrictions ?? [];

        if (isset($restrictions['domain']) && 
            isset($validationData['domain']) && 
            $restrictions['domain'] !== $validationData['domain']) {
            throw new Exception('Invalid domain');
        }

        if (isset($restrictions['environment']) && 
            isset($validationData['environment']) && 
            $restrictions['environment'] !== $validationData['environment']) {
            throw new Exception('Invalid environment');
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
     * @throws Exception
     */
    public function findByPurchaseCode(string $purchaseCode): License
    {
        $license = $this->repository->findByPurchaseCode($purchaseCode);

        if (!$license) {
            throw new Exception("No license found for purchase code: {$purchaseCode}");
        }

        return $license;
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

    private function generateCacheKey(string $licenseKey): string 
    {
        // Generate a shorter cache key using hash
        return 'license:' . md5($licenseKey);
    }
}