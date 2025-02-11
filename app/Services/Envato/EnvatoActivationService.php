<?php

namespace App\Services\Envato;

use App\Exceptions\EnvatoActivationException;
use App\Models\License;
use App\Models\EnvatoPurchase;
use App\Models\LicenseActivation;
use App\Models\LicenseDomain;
use App\Services\LicenseActivationService;
use Exception;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnvatoActivationService
{
    private const CURRENT_TIME = '2025-02-11 06:01:57';
    private const CURRENT_USER = 'maab16';
    
    private const LOCAL_DOMAIN_PATTERNS = [
        '/\.test$/',
        '/\.local$/',
        '/\.localhost$/',
        '/^localhost/',
        '/^127\.0\.0\.1$/',
        '/^::1$/',
    ];

    public function __construct(
        private readonly LicenseActivationService $activationService,
        private readonly EnvatoLicenseService $envatoService
    ) {}

    public function isLocalDomain(string $domain): bool
    {
        $domain = strtolower($domain);
        
        foreach (self::LOCAL_DOMAIN_PATTERNS as $pattern) {
            if (preg_match($pattern, $domain)) {
                return true;
            }
        }

        return false;
    }

    private function validateEnvatoLicense(License $license): void
    {
        // if (! in_array($license->type, ['regular', 'extended'])) {
        //     throw new EnvatoActivationException('Invalid license type');
        // }

        if ($license->status !== 'active' ) {
            throw new EnvatoActivationException('License is not active');
        }

        // if ($license->valid_until && Carbon::parse($license->valid_until)->isPast()) {
        //     throw new EnvatoActivationException('License has expired');
        // }
    }

    private function validateDomainLimits(License $license, string $domain): void
    {
        $isLocal = $this->isLocalDomain($domain);
        
        // Get active production domains
        $activeProductionDomains = $license->domains()
            ->where('is_active', true)
            ->get()
            ->filter(fn ($d) => !$this->isLocalDomain($d->domain));

        

        // Check domain limits based on license type
        $isExtended = in_array($license->type, ['extended']);
        $maxDomains = $isExtended ? 5 : 1;

        // dd($license->domains);

        if (!$isLocal && $activeProductionDomains->count() >= $maxDomains) {
            throw new EnvatoActivationException(
                "Production domain limit reached. Maximum allowed: {$maxDomains}"
            );
        }
    }

    private function createActivation(License $license, array $data): LicenseActivation
    {
        return $license->activations()->create([
            'id' => Str::uuid(),
            'activation_token' => Str::random(8) . '_' . Str::random(40),
            'type' => 'domain',
            'domain' => $data['domain'],
            'device_identifier' => $data['device_identifier'],
            'device_name' => $data['device_name'],
            'hardware_hash' => json_encode($data['hardware_info']),
            'ip_address' => request()->ip(),
            'is_active' => true,
            'activated_at' => self::CURRENT_TIME,
            'created_by' => self::CURRENT_USER,
            'updated_by' => self::CURRENT_USER
        ]);
    }

    private function createDomainRecord(
        License $license,
        LicenseActivation $activation,
        string $domain
    ): LicenseDomain {
        return $license->domains()->updateOrCreate(
            ['domain' => $domain],
            [
                'id' => Str::uuid(),
                'activation_id' => $activation->id,
                'domain' => $domain,
                'verification_hash' => Str::random(64),
                'verification_method' => 'dns',
                'is_active' => true,
                'activated_at' => self::CURRENT_TIME,
                'last_checked_at' => self::CURRENT_TIME,
                'created_by' => self::CURRENT_USER,
                'updated_by' => self::CURRENT_USER
            ]
        );
    }

    public function activateEnvatoLicense( License $license, array $data): array
    {
        return DB::transaction(function () use ($license, $data) {
            try {
                // Validate license
                $this->validateEnvatoLicense($license);

                // Validate domain limits
                $this->validateDomainLimits($license, $data['domain']);

                // Create activation
                $activation = $this->createActivation($license, $data);

                // Create domain record
                $domain = $this->createDomainRecord($license, $activation, $data['domain']);
                // Update license seats
                $license->increment('activated_seats');

                return [
                    'status' => 'activated',
                    'activation_id' => $activation->id,
                    'activation_token' => $activation->activation_token,
                    'domain' => $domain->domain,
                    'activated_at' => $activation->activated_at,
                    'expires_at' => $license->valid_until,
                    'seats' => [
                        'total' => $license->total_seats,
                        'activated' => $license->activated_seats,
                        'remaining' => $license->total_seats - $license->activated_seats
                    ]
                ];

            } catch (\Exception $e) {
                Log::error('License activation failed', [
                    'license_id' => $license->id,
                    'domain' => $data['domain'],
                    'error' => $e->getMessage(),
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);
                throw $e;
            }
        });
    }

    public function activateWithPurchaseCode(string $purchaseCode, array $data): array
    {
        return DB::transaction(function () use ($purchaseCode, $data) {
            try {
                Log::info('Starting purchase code activation', [
                    'purchase_code' => $purchaseCode,
                    'domain' => $data['domain'],
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                $itemId = $data['item_id'];

                // Check if license already exists
                $license = $this->findExistingLicense($purchaseCode);

                if (!$license) {
                    // Convert purchase code to license if not exists
                    $license = $this->envatoService->convertToLicense($purchaseCode, $itemId);
                    
                    Log::info('New license created', [
                        'purchase_code' => $purchaseCode,
                        'license_id' => $license->id,
                        'timestamp' => self::CURRENT_TIME,
                        'user' => self::CURRENT_USER
                    ]);
                }

                // Validate and activate the license
                // $activationResult = $this->activateEnvatoLicense($license, $data);
                $activationResult = $this->activationService->activateLicense($license, $data);

                $result = array_merge($activationResult, [
                    'verifier' => $license->key,
                    'license_details' => [
                        'id' => $license->id,
                        'type' => $license->type,
                        'valid_until' => $license->valid_until,
                        'features' => $license->features
                    ]
                ]);

                Log::info('Purchase code activation completed', [
                    'purchase_code' => $purchaseCode,
                    'license_id' => $license->id,
                    'domain' => $data['domain'],
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                return $result;

            } catch (\Exception $e) {
                Log::error('Purchase code activation failed', [
                    'purchase_code' => $purchaseCode,
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString(),
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);
                throw $e;
            }
        });
    }

    private function findExistingLicense(string $purchaseCode): ?License
    {
        return License::where('source_purchase_code', $purchaseCode)
            ->first();
    }

    public function deactivateEnvatoDomain(License $license, string $domain): void
    {
        DB::transaction(function () use ($license, $domain) {
            $domainRecord = $license->domains()
                ->where('domain', $domain)
                ->where('is_active', true)
                ->firstOrFail();

            // Deactivate domain
            $domainRecord->update([
                'is_active' => false,
                'deactivated_at' => self::CURRENT_TIME,
                'deactivated_by' => self::CURRENT_USER,
                'updated_by' => self::CURRENT_USER
            ]);

            // Deactivate associated activation
            $domainRecord->activation->update([
                'is_active' => false,
                'deactivated_at' => self::CURRENT_TIME,
                'updated_by' => self::CURRENT_USER
            ]);

            // Decrement license seats
            $license->decrement('activated_seats');
        });
    }

    private function validateEnvatoRestrictions(License $license, array $activationData): void
    {
        // Get Envato purchase details from license metadata
        $purchaseCode = $license->metadata['envato_purchase_code'] ?? null;
        
        if (!$purchaseCode) {
            throw new Exception(
                'Invalid license: No Envato purchase code found'
            );
        }

        $envatoPurchase = EnvatoPurchase::where('purchase_code', $purchaseCode)
            ->first();

        if (!$envatoPurchase) {
            throw new Exception(
                'Invalid license: Envato purchase not found'
            );
        }

        // Check if domain is provided
        if (empty($activationData['domain'])) {
            throw new Exception(
                'Domain is required for Envato license activation'
            );
        }

        $domain = strtolower($activationData['domain']);

        // Check active domains (excluding local domains)
        $activeProductionDomains = $this->getActiveProductionDomains($license);

        // If this is not a local domain and we already have a production domain
        if (!$this->isLocalDomain($domain) && $activeProductionDomains->isNotEmpty()) {
            throw new Exception(
                'Regular License allows only one production domain. ' .
                'Current active domain: ' . $activeProductionDomains->first()->domain
            );
        }

        // Check if this specific domain is already activated
        $existingDomain = $license->domains()
            ->where('domain', $domain)
            ->where('is_active', true)
            ->first();

        if ($existingDomain) {
            throw new Exception(
                "Domain {$domain} is already activated on this license"
            );
        }

        // For Extended License, check if it's within allowed domain limit
        if ($envatoPurchase->license_type === 'Extended License') {
            $totalActiveDomains = $license->domains()
                ->where('is_active', true)
                ->count();

            if ($totalActiveDomains >= 5) { // Example limit for Extended License
                throw new Exception(
                    'Extended License domain limit reached (5 domains maximum)'
                );
            }
        }

        Log::info('Envato restrictions validated successfully', [
            'license_id' => $license->id,
            'domain' => $domain,
            'is_local' => $this->isLocalDomain($domain),
            'license_type' => $envatoPurchase->license_type,
            'timestamp' => self::CURRENT_TIME,
            'user' => self::CURRENT_USER
        ]);
    }

    private function getActiveProductionDomains($license)
    {
        return $license->domains()
            ->where('is_active', true)
            ->get()
            ->filter(function ($domain) {
                return !$this->isLocalDomain($domain->domain);
            });
    }
}