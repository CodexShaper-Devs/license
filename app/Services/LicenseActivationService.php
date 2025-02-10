<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseDomain;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class LicenseActivationService
{
    private const CURRENT_TIME = '2025-02-10 07:31:16';
    private const CURRENT_USER = 'maab16';

    public function __construct(
        private readonly LicenseDomainService $domainService,
        private readonly LicenseSecurityService $securityService
    ) {}

    private function validateLicenseStatus(License $license): void
    {
        Log::info('Validating license status', [
            'license_id' => $license->id,
            'current_status' => $license->status,
            'valid_until' => $license->valid_until,
            'timestamp' => self::CURRENT_TIME,
            'user' => self::CURRENT_USER
        ]);

        if ($license->status !== 'active' && !$license->valid_until) {
            throw new Exception(
                "License is not active"
            );
        }

        if ($license->status !== 'active' && $license->status !== 'pending') {
            throw new Exception(
                "License cannot be activated. Current status: {$license->status}"
            );
        }

        if ($license->valid_until && Carbon::parse($license->valid_until)->isPast()) {
            throw new Exception(
                "License has expired on {$license->valid_until}"
            );
        }
    }

    private function validateSeats(License $license): void
    {
        Log::info('Validating license seats', [
            'license_id' => $license->id,
            'purchased_seats' => $license->purchased_seats,
            'activated_seats' => $license->activated_seats,
            'timestamp' => self::CURRENT_TIME,
            'user' => self::CURRENT_USER
        ]);

        // Get count of active activations
        $activeActivationsCount = $license->activations()
            ->where('is_active', true)
            ->count();

        Log::info('Current active activations', [
            'license_id' => $license->id,
            'active_count' => $activeActivationsCount,
            'timestamp' => self::CURRENT_TIME,
            'user' => self::CURRENT_USER
        ]);

        if ($license->purchased_seats !== -1 && $activeActivationsCount >= $license->purchased_seats) {
            throw new Exception(
                "License seat limit reached. Total seats: {$license->purchased_seats}, " .
                "Active seats: {$activeActivationsCount}"
            );
        }

        // Validate if this domain is already activated on this license
        if (!empty($license->domain)) {
            $existingDomainActivation = $license->activations()
                ->where('domain', $license->domain)
                ->where('is_active', true)
                ->first();

            if ($existingDomainActivation) {
                throw new Exception(
                    "Domain {$license->domain} is already activated on this license"
                );
            }
        }
    }

    public function activateLicense(License $license, array $activationData): array
    {
        return DB::transaction(function () use ($license, $activationData) {
            try {
                Log::info('Starting license activation', [
                    'license_id' => $license->id,
                    'current_status' => $license->status,
                    'activation_data' => $activationData,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                // Validate license status first
                $this->validateLicenseStatus($license);

                // Then validate seats
                $this->validateSeats($license);

                // Create activation record
                $activation = $this->createActivationRecord($license, $activationData);

                Log::info('Created activation record', [
                    'license_id' => $license->id,
                    'activation_id' => $activation->id,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                // Create domain record if domain is provided
                if (!empty($activationData['domain'])) {
                    $normalizedDomain = $this->domainService->validateDomain(
                        $license, 
                        $activationData['domain']
                    );

                    $domainRecord = $this->createDomainRecord(
                        $license,
                        $activation->id,
                        $normalizedDomain
                    );
                }

                // Update license status if it was pending
                if ($license->status === 'pending') {
                    $license->status = 'active';
                }

                // Update activated seats count
                $license->activated_seats = $license->activations()
                    ->where('is_active', true)
                    ->count();

                $license->save();

                $response = [
                    'status' => 'activated',
                    'activation_id' => $activation->id,
                    'activation_token' => $activation->activation_token,
                    'domain' => $activation->domain,
                    'activated_at' => $activation->activated_at,
                    'expires_at' => $license->valid_until,
                    'seats' => [
                        'total' => $license->purchased_seats,
                        'activated' => $license->activated_seats,
                        'remaining' => $license->purchased_seats === -1 ? 
                            'unlimited' : 
                            ($license->purchased_seats - $license->activated_seats)
                    ]
                ];

                Log::info('License activation completed successfully', [
                    'license_id' => $license->id,
                    'activation_id' => $activation->id,
                    'response' => $response,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                return $response;

            } catch (\Exception $e) {
                Log::error('License activation failed', [
                    'license_id' => $license->id,
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString(),
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);
                throw $e;
            }
        });
    }

    private function createDomainRecord(
        License $license,
        string $activationId,
        string $domain
    ): LicenseDomain {
        // First create the base record

        $domainRecord = LicenseDomain::where('domain', $domain)->first();

        if (! $domainRecord) {
            $domainRecord = new LicenseDomain();
        }
        $domainRecord->id = Str::uuid();
        $domainRecord->license_id = $license->id;
        $domainRecord->activation_id = $activationId; // Set this explicitly
        $domainRecord->domain = $domain;
        $domainRecord->verification_hash = Str::random(64);
        $domainRecord->verification_method = 'dns';
        $domainRecord->is_active = true;
        $domainRecord->activated_at = self::CURRENT_TIME;
        $domainRecord->last_checked_at = self::CURRENT_TIME;
        $domainRecord->created_by = self::CURRENT_USER;
        $domainRecord->updated_by = self::CURRENT_USER;

        Log::info('Creating domain record', [
            'license_id' => $license->id,
            'activation_id' => $activationId,
            'domain' => $domain,
            'domain_data' => $domainRecord->toArray(),
            'timestamp' => self::CURRENT_TIME,
            'user' => self::CURRENT_USER
        ]);

        try {
            // Save the record
            $domainRecord->save();

            Log::info('Domain record created successfully', [
                'license_id' => $license->id,
                'activation_id' => $activationId,
                'domain_id' => $domainRecord->id,
                'domain' => $domain,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return $domainRecord;

        } catch (\Exception $e) {
            Log::error('Failed to create domain record', [
                'license_id' => $license->id,
                'activation_id' => $activationId,
                'domain' => $domain,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
            throw $e;
        }
    }

    private function createActivationRecord(License $license, array $data): LicenseActivation
    {
        $activation = new LicenseActivation();
        $activation->id = Str::uuid();
        $activation->activation_token = Str::random(8) . '_' . Str::random(40);
        $activation->type = $data['type'] ?? 'domain';
        $activation->device_identifier = $data['device_identifier'] ?? null;
        $activation->device_name = $data['device_name'] ?? null;
        $activation->hardware_hash = isset($data['hardware_info']) ? 
            json_encode($data['hardware_info']) : null;
        $activation->system_info = isset($data['system_info']) ? 
            json_encode($data['system_info']) : null;
        $activation->ip_address = request()->ip();
        $activation->is_active = true;
        $activation->activated_at = self::CURRENT_TIME;
        $activation->created_by = self::CURRENT_USER;
        $activation->updated_by = self::CURRENT_USER;

        $license->activations()->save($activation);

        return $activation;
    }
    
    private function generateActivationToken(): string
    {
        return Str::random(8) . '_' . Str::random(40);
    }

    private function generateActivationResponse(
        License $license, 
        LicenseActivation $activation
    ): array {
        return [
            'status' => 'activated',
            'activation_id' => $activation->id,
            'activation_token' => $activation->activation_token,
            'type' => $activation->type,
            'domain' => $activation->domain,
            'activated_at' => $activation->activated_at,
            'expires_at' => $license->valid_until,
            'next_check_in' => Carbon::parse(self::CURRENT_TIME)
                ->addDays($license->product->check_in_interval_days)
                ->format('Y-m-d H:i:s')
        ];
    }

    private function checkDomainAvailability(License $license, string $domain): void
    {
        $existingDomain = LicenseDomain::where('domain', $domain)
            ->where('is_active', true)
            ->first();

        if ($existingDomain) {
            if ($existingDomain->license_id === $license->id) {
                throw new RuntimeException("Domain {$domain} is already activated on this license");
            } else {
                throw new RuntimeException("Domain {$domain} is already activated on another license");
            }
        }
    }
}