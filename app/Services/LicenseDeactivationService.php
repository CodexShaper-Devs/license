<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseDomain;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Carbon\Carbon;

class LicenseDeactivationService
{
    private const CURRENT_TIME = '2025-02-10 07:56:14';
    private const CURRENT_USER = 'maab16';

    public function deactivateByDomain(
        License $license, 
        string $domain, 
        string $activationToken
    ): array {
        return DB::transaction(function () use ($license, $domain, $activationToken) {
            try {
                Log::info('Starting domain-based deactivation', [
                    'license_id' => $license->id,
                    'domain' => $domain,
                    'activation_token' => $activationToken,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                // Find domain record
                $domainRecord = $license->domains()
                    ->where('domain', $domain)
                    ->where('is_active', true)
                    ->first();

                if (!$domainRecord) {
                    throw new RuntimeException("No active domain found: {$domain}");
                }

                // Find and validate activation
                $activation = $license->activations()
                    ->where('id', $domainRecord->activation_id)
                    ->where('activation_token', $activationToken)
                    ->where('is_active', true)
                    ->first();

                if (!$activation) {
                    throw new RuntimeException(
                        "No matching active activation found for domain: {$domain} with provided token"
                    );
                }

                // Verify token matches
                if ($activation->activation_token !== $activationToken) {
                    throw new RuntimeException('Invalid activation token for this domain');
                }

                // Deactivate domain
                $domainRecord->update([
                    'is_active' => false,
                    'deactivated_at' => self::CURRENT_TIME,
                    'deactivated_by' => self::CURRENT_USER,
                    'updated_at' => self::CURRENT_TIME,
                    'updated_by' => self::CURRENT_USER
                ]);

                // Deactivate activation
                $activation->update([
                    'is_active' => false,
                    'deactivated_at' => self::CURRENT_TIME,
                    'deactivation_reason' => 'Domain deactivation requested with valid token',
                    'deactivated_by' => self::CURRENT_USER,
                    'updated_at' => self::CURRENT_TIME,
                    'updated_by' => self::CURRENT_USER
                ]);

                // Decrease license seats count
                $license->decrement('activated_seats');

                $response = [
                    'status' => 'deactivated',
                    'type' => 'domain',
                    'domain' => $domain,
                    'activation_id' => $activation->id,
                    'deactivated_at' => self::CURRENT_TIME
                ];

                Log::info('Domain-based deactivation completed', [
                    'license_id' => $license->id,
                    'domain' => $domain,
                    'activation_id' => $activation->id,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                return $response;

            } catch (\Exception $e) {
                Log::error('Domain-based deactivation failed', [
                    'license_id' => $license->id,
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);
                throw $e;
            }
        });
    }

    public function deactivateEntireLicense(License $license, string $reason = 'Complete license deactivation'): array
    {
        return DB::transaction(function () use ($license, $reason) {
            try {
                Log::info('Starting complete license deactivation', [
                    'license_id' => $license->id,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                // Count active seats before deactivation
                $activeSeats = $license->activations()
                    ->where('is_active', true)
                    ->count();

                if ($activeSeats === 0) {
                    throw new RuntimeException('No active seats found for this license');
                }

                // Deactivate all domains
                $license->domains()
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'deactivated_at' => self::CURRENT_TIME,
                        'deactivated_by' => self::CURRENT_USER,
                        'updated_at' => self::CURRENT_TIME,
                        'updated_by' => self::CURRENT_USER
                    ]);

                // Deactivate all activations
                $license->activations()
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'deactivated_at' => self::CURRENT_TIME,
                        'deactivation_reason' => $reason,
                        'deactivated_by' => self::CURRENT_USER,
                        'updated_at' => self::CURRENT_TIME,
                        'updated_by' => self::CURRENT_USER
                    ]);

                // Reset activated seats count
                $license->update([
                    'activated_seats' => 0,
                    'updated_at' => self::CURRENT_TIME,
                    'updated_by' => self::CURRENT_USER
                ]);

                $response = [
                    'status' => 'deactivated',
                    'type' => 'complete',
                    'deactivated_seats' => $activeSeats,
                    'deactivated_at' => self::CURRENT_TIME,
                    'reason' => $reason
                ];

                Log::info('Complete license deactivation successful', [
                    'license_id' => $license->id,
                    'deactivated_seats' => $activeSeats,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                return $response;

            } catch (\Exception $e) {
                Log::error('Complete license deactivation failed', [
                    'license_id' => $license->id,
                    'error' => $e->getMessage(),
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);
                throw $e;
            }
        });
    }

    public function deactivateLicense(
        License $license,
        string $activationToken,
        ?string $reason = null
    ): array {
        return DB::transaction(function () use ($license, $activationToken, $reason) {
            try {
                Log::info('Starting license deactivation', [
                    'license_id' => $license->id,
                    'activation_token' => $activationToken,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                // Find activation
                $activation = $this->findActivation($license, $activationToken);

                // Check if already deactivated
                if (!$activation->is_active) {
                    throw new RuntimeException('License activation is already deactivated');
                }

                // Deactivate domain if exists
                if ($activation->domain) {
                    $this->deactivateDomain($license, $activation);
                }

                // Deactivate activation
                $this->deactivateActivation($activation, $reason);

                // Decrease license seats count
                $license->decrement('activated_seats');

                $response = [
                    'status' => 'deactivated',
                    'activation_id' => $activation->id,
                    'type' => $activation->type,
                    'domain' => $activation->domain,
                    'deactivated_at' => self::CURRENT_TIME,
                    'reason' => $reason
                ];

                Log::info('License deactivation completed successfully', [
                    'license_id' => $license->id,
                    'activation_id' => $activation->id,
                    'domain' => $activation->domain,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                return $response;

            } catch (\Exception $e) {
                Log::error('License deactivation failed', [
                    'license_id' => $license->id,
                    'activation_token' => $activationToken,
                    'error' => $e->getMessage(),
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);
                throw $e;
            }
        });
    }

    private function findActivation(License $license, string $activationToken): LicenseActivation
    {
        $activation = $license->activations()
            ->where('activation_token', $activationToken)
            ->first();

        if (!$activation) {
            throw new RuntimeException('Invalid activation token');
        }

        return $activation;
    }

    private function deactivateDomain(License $license, LicenseActivation $activation): void
    {
        $domain = $license->domains()
            ->where('activation_id', $activation->id)
            ->where('is_active', true)
            ->first();

        if ($domain) {
            $domain->update([
                'is_active' => false,
                'deactivated_at' => self::CURRENT_TIME,
                'deactivated_by' => self::CURRENT_USER,
                'updated_at' => self::CURRENT_TIME,
                'updated_by' => self::CURRENT_USER
            ]);

            Log::info('Domain deactivated', [
                'license_id' => $license->id,
                'activation_id' => $activation->id,
                'domain' => $domain->domain,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);
        }
    }

    private function deactivateActivation(LicenseActivation $activation, ?string $reason): void
    {
        $activation->update([
            'is_active' => false,
            'deactivated_at' => self::CURRENT_TIME,
            'deactivation_reason' => $reason,
            'deactivated_by' => self::CURRENT_USER,
            'updated_at' => self::CURRENT_TIME,
            'updated_by' => self::CURRENT_USER
        ]);

        Log::info('Activation deactivated', [
            'activation_id' => $activation->id,
            'reason' => $reason,
            'timestamp' => self::CURRENT_TIME,
            'user' => self::CURRENT_USER
        ]);
    }

    public function bulkDeactivate(License $license, array $filters = []): array
    {
        return DB::transaction(function () use ($license, $filters) {
            try {
                Log::info('Starting bulk deactivation', [
                    'license_id' => $license->id,
                    'filters' => $filters,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                $query = $license->activations()->where('is_active', true);

                // Apply filters
                if (!empty($filters['domain'])) {
                    $query->where('domain', 'LIKE', '%' . $filters['domain'] . '%');
                }
                if (!empty($filters['device_identifier'])) {
                    $query->where('device_identifier', $filters['device_identifier']);
                }
                if (!empty($filters['type'])) {
                    $query->where('type', $filters['type']);
                }

                $activations = $query->get();
                $deactivatedCount = 0;

                foreach ($activations as $activation) {
                    if ($activation->domain) {
                        $this->deactivateDomain($license, $activation);
                    }
                    $this->deactivateActivation($activation, 'Bulk deactivation');
                    $deactivatedCount++;
                }

                if ($deactivatedCount > 0) {
                    $license->decrement('activated_seats', $deactivatedCount);
                }

                $response = [
                    'status' => 'deactivated',
                    'deactivated_count' => $deactivatedCount,
                    'deactivated_at' => self::CURRENT_TIME
                ];

                Log::info('Bulk deactivation completed', [
                    'license_id' => $license->id,
                    'count' => $deactivatedCount,
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                return $response;

            } catch (\Exception $e) {
                Log::error('Bulk deactivation failed', [
                    'license_id' => $license->id,
                    'error' => $e->getMessage(),
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);
                throw $e;
            }
        });
    }
}