<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseDomain;
use App\Services\Validators\DomainValidator;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class LicenseDomainService
{
    private const CURRENT_TIME = '2025-02-10 08:08:37';
    private const CURRENT_USER = 'maab16';

    public function __construct(
        private readonly DomainValidator $validator,
        private readonly LicenseSecurityService $securityService
    ) {}

    public function validateDomain(License $license, string $domain): string
    {
        try {
            Log::info('Starting domain validation', [
                'license_id' => $license->id,
                'domain' => $domain,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            // Normalize domain
            $normalizedDomain = $this->normalizeDomain($domain);

            // Basic domain format validation
            if (!$this->validator->isValidDomain($normalizedDomain)) {
                throw new Exception("Invalid domain format: {$normalizedDomain}");
            }

            // Check domain restrictions
            $this->validateDomainRestrictions($license, $normalizedDomain);

            // Check if column exists before querying with it
            $hasActivationId = Schema::hasColumn('license_domains', 'activation_id');

            // Query for active domain usage
            $query = LicenseDomain::query()
                ->where('domain', $normalizedDomain)
                ->where('is_active', true);

            if ($hasActivationId) {
                $query->whereHas('licenseActivation', function($q) {
                    $q->where('is_active', true);
                });
            }

            $query->whereHas('license', function($q) use ($license) {
                $q->where('id', '!=', $license->id)
                    ->where('is_active', true)
                    ->where('status', 'active');
            });

            $existingDomain = $query->first();

            if ($existingDomain) {
                // Log detailed information about the conflicting domain
                Log::warning('Domain usage conflict detected', [
                    'domain' => $normalizedDomain,
                    'attempting_license_id' => $license->id,
                    'existing_license_id' => $existingDomain->license_id,
                    'existing_activation_id' => $hasActivationId ? $existingDomain->activation_id : null,
                    'existing_activation_date' => $existingDomain->activated_at,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                throw new Exception(
                    "Domain {$normalizedDomain} is already activated on another license"
                );
            }

            // Check if domain is already active on this license
            $query = $license->domains()
                ->where('domain', $normalizedDomain)
                ->where('is_active', true);

            if ($hasActivationId) {
                $query->whereHas('licenseActivation', function($q) {
                    $q->where('is_active', true);
                });
            }

            $existingLicenseDomain = $query->first();

            if ($existingLicenseDomain) {
                Log::warning('Domain already active on this license', [
                    'license_id' => $license->id,
                    'domain' => $normalizedDomain,
                    'activation_id' => $hasActivationId ? $existingLicenseDomain->activation_id : null,
                    'activated_at' => $existingLicenseDomain->activated_at,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                throw new Exception(
                    "Domain {$normalizedDomain} is already active on this license"
                );
            }

            // Clean up any inactive domains
            $cleanupQuery = $license->domains()
                ->where('domain', $normalizedDomain)
                ->where(function($query) use ($hasActivationId) {
                    $query->where('is_active', false);
                    if ($hasActivationId) {
                        $query->orWhereDoesntHave('licenseActivation', function($q) {
                            $q->where('is_active', true);
                        });
                    }
                });

            $cleanupQuery->update([
                'is_active' => false,
                'deactivated_at' => self::CURRENT_TIME,
                'deactivated_by' => self::CURRENT_USER,
                'updated_at' => self::CURRENT_TIME,
                'updated_by' => self::CURRENT_USER
            ]);

            Log::info('Domain validation successful', [
                'license_id' => $license->id,
                'domain' => $normalizedDomain,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return $normalizedDomain;

        } catch (\Exception $e) {
            Log::error('Domain validation failed', [
                'license_id' => $license->id,
                'domain' => $domain,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            throw $e;
        }
    }

    private function normalizeDomain(string $domain): string
    {
        // Remove protocol
        $domain = preg_replace('#^https?://#', '', $domain);
        
        // Remove path and query
        $domain = strtok($domain, '/');
        
        // Convert to lowercase
        $domain = strtolower($domain);
        
        // Remove www prefix
        $domain = preg_replace('/^www\./', '', $domain);
        
        // Remove trailing dot
        $domain = rtrim($domain, '.');
        
        // Remove spaces and trim
        $domain = trim($domain);
        
        return $domain;
    }

    private function validateDomainRestrictions(License $license, string $domain): void
    {
        // Check if local domains are allowed
        if ($this->validator->isLocalDomain($domain) && !$license->plan->allow_local_domains) {
            throw new Exception('Local domains are not allowed with this license');
        }

        // Check domain patterns if specified
        if (!empty($license->plan->allowed_domain_patterns)) {
            $this->validateDomainPatterns($domain, json_decode($license->plan->allowed_domain_patterns, true));
        }
    }

    private function validateDomainPatterns(string $domain, array $patterns): void
    {
        if (empty($patterns)) {
            return;
        }

        $matches = false;
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $domain)) {
                $matches = true;
                break;
            }
        }

        if (!$matches) {
            throw new Exception(
                "Domain {$domain} does not match allowed patterns: " . implode(', ', $patterns)
            );
        }
    }

    public function isDomainAvailable(string $domain): bool
    {
        $normalizedDomain = $this->normalizeDomain($domain);
        
        return !LicenseDomain::where('domain', $normalizedDomain)
            ->where('is_active', true)
            ->exists();
    }

    private function validateDomainAvailability(string $domain): void
    {
        $existingDomain = LicenseDomain::where('domain', $domain)
            ->where('is_active', true)
            ->first();

        if ($existingDomain) {
            Log::warning('Domain already in use', [
                'domain' => $domain,
                'existing_license_id' => $existingDomain->license_id,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            throw new Exception("Domain {$domain} is already activated under another license");
        }
    }

    public function addDomain(License $license, string $domain): LicenseDomain
    {
        try {
            // Normalize and validate domain
            $domain = $this->normalizeDomain($domain);
            $this->validateDomain($license, $domain);

            // Generate domain verification hash
            $verificationHash = $this->securityService->generateDomainValidationHash(
                $domain,
                $license->key
            );

            // Create domain record
            $licenseDomain = $license->domains()->create([
                'domain' => $domain,
                'verification_hash' => $verificationHash,
                'verification_method' => 'dns',
                'is_active' => false,
                'activated_at' => null,
                'last_checked_at' => null,
                'expires_at' => null,
                'created_by' => self::CURRENT_USER,
                'created_at' => self::CURRENT_TIME,
                'updated_at' => self::CURRENT_TIME
            ]);

            Log::info('Domain added successfully', [
                'license_id' => $license->id,
                'domain_id' => $licenseDomain->id,
                'domain' => $domain,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return $licenseDomain;

        } catch (\Exception $e) {
            Log::error('Failed to add domain', [
                'license_id' => $license->id,
                'domain' => $domain,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            throw new RuntimeException('Failed to add domain: ' . $e->getMessage());
        }
    }

    public function verifyDomain(License $license, string $domain): bool
    {
        try {
            $domain = $this->normalizeDomain($domain);
            
            $licenseDomain = $license->domains()
                ->where('domain', $domain)
                ->first();

            if (!$licenseDomain) {
                throw new RuntimeException("Domain not found: {$domain}");
            }

            // Verify domain ownership
            $isVerified = $this->securityService->verifyDomainValidation(
                $domain,
                $licenseDomain->verification_hash,
                $license->key
            );

            if ($isVerified) {
                $licenseDomain->update([
                    'is_active' => true,
                    'activated_at' => self::CURRENT_TIME,
                    'last_checked_at' => self::CURRENT_TIME,
                    'expires_at' => Carbon::parse(self::CURRENT_TIME)
                        ->addDays(30)
                        ->format('Y-m-d H:i:s'),
                    'updated_by' => self::CURRENT_USER
                ]);

                Log::info('Domain verified successfully', [
                    'license_id' => $license->id,
                    'domain_id' => $licenseDomain->id,
                    'domain' => $domain,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);
            }

            return $isVerified;

        } catch (\Exception $e) {
            Log::error('Domain verification failed', [
                'license_id' => $license->id,
                'domain' => $domain,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            return false;
        }
    }

    private function validateDomainCount(License $license, string $domain): void
    {
        $activeDomains = $license->domains()
            ->where('is_active', true)
            ->count();

        $maxDomainsPerSeat = $license->plan->subdomains_per_seat ?: 1;
        $totalAllowedDomains = $license->purchased_seats * $maxDomainsPerSeat;

        if ($activeDomains >= $totalAllowedDomains) {
            throw new RuntimeException(
                "Maximum domain limit reached. Allowed: {$totalAllowedDomains}, Current: {$activeDomains}"
            );
        }

        // Check for same root domain if subdomains are not allowed
        if (!$license->plan->allow_subdomains) {
            $rootDomain = $this->getRootDomain($domain);
            $existingRootDomains = $license->domains()
                ->where('is_active', true)
                ->get()
                ->map(fn($d) => $this->getRootDomain($d->domain))
                ->unique();

            if ($existingRootDomains->contains($rootDomain)) {
                throw new RuntimeException('Multiple domains from the same root domain are not allowed');
            }
        }
    }

    private function getRootDomain(string $domain): string
    {
        $parts = explode('.', $domain);
        if (count($parts) > 2) {
            // Check for country codes like .co.uk
            if (strlen($parts[count($parts) - 2]) === 2 && strlen($parts[count($parts) - 1]) === 2) {
                return implode('.', array_slice($parts, -3));
            }
            return implode('.', array_slice($parts, -2));
        }
        return $domain;
    }

    public function refreshDomainVerification(License $license): void
    {
        try {
            $domains = $license->domains()
                ->where('is_active', true)
                ->where('expires_at', '<', self::CURRENT_TIME)
                ->get();

            foreach ($domains as $domain) {
                if ($this->verifyDomain($license, $domain->domain)) {
                    Log::info('Domain verification refreshed', [
                        'license_id' => $license->id,
                        'domain_id' => $domain->id,
                        'domain' => $domain->domain,
                        'timestamp' => self::CURRENT_TIME,
                        'user' => self::CURRENT_USER
                    ]);
                } else {
                    $domain->update([
                        'is_active' => false,
                        'updated_by' => self::CURRENT_USER
                    ]);

                    Log::warning('Domain verification failed during refresh', [
                        'license_id' => $license->id,
                        'domain_id' => $domain->id,
                        'domain' => $domain->domain,
                        'timestamp' => self::CURRENT_TIME,
                        'user' => self::CURRENT_USER
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Domain verification refresh failed', [
                'license_id' => $license->id,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            throw $e;
        }
    }
}