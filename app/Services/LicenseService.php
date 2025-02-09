<?php

namespace App\Services;

use App\Models\License;
use App\Repositories\LicenseRepository;
use App\Exceptions\LicenseValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LicenseService
{

    public function __construct(
        private readonly LicenseRepository $repository,
        private readonly EncryptionService $encryption,
        private readonly KeyManagementService $keyManagement
    ) {}

    public function createLicense(array $data): License
    {
        return DB::transaction(function () use ($data) {
            $timestamp = Carbon::now();
            $currentUser = Auth::check() ? Auth::user()->id : 'system';
        
            // Generate unique license key
            $data['key'] = $this->generateLicenseKey();
            
            // Generate and ensure keys exist
            $keyId = Str::random(32);
            $this->keyManagement->generateKeys($keyId);
            
            // Set key IDs
            $data['encryption_key_id'] = $keyId;
            $data['auth_key_id'] = $keyId;

            // Set timestamps
            $data['created_at'] = $timestamp;
            $data['updated_at'] = $timestamp;
            $data['created_by'] = $currentUser;

            // Create license record
            $license = $this->repository->create($data);

            // Create data for signature
            $signatureData = [
                'key' => $license->key,
                'user_id' => $license->user_id,
                'product_id' => $license->product_id,
                'valid_until' => $license->valid_until->toIso8601String(),
                'features' => $license->features,
                'restrictions' => $license->restrictions,
                'created_at' => $timestamp->toIso8601String(),
                'created_by' => $currentUser
            ];

            // First encrypt the data
            $encryptedData = $this->encryption->encrypt(
                $signatureData,
                $license->encryption_key_id
            );

            // Then sign the encrypted data
            $signature = $this->encryption->sign(
                $encryptedData,
                $license->auth_key_id
            );

            // Store both the original data and encrypted version
            $license->update([
                'signature' => $signature,
                'metadata' => array_merge($data['metadata'] ?? [], [
                    '_signature' => [
                        'data' => $signatureData,
                        'encrypted' => $encryptedData
                    ]
                ])
            ]);

            return $license;
        });
    }

    public function activateLicense(string $licenseKey, array $activationData): array
    {
        return DB::transaction(function () use ($licenseKey, $activationData) {
            $timestamp = Carbon::now();
            $currentUser = Auth::check() ? Auth::user()->id : 'system';;
            
            $license = $this->repository->findByKey($licenseKey);
    
            if (!$license) {
                throw new LicenseValidationException('License not found');
            }
    
            if (!$this->validateLicenseSignature($license)) {
                throw new LicenseValidationException('Invalid license signature');
            }
    
            if (!$license->isValid()) {
                throw new LicenseValidationException('License is not valid or has expired');
            }
    
            // Normalize and validate domain
            $requestDomain = $this->normalizeDomain($activationData['domain']);
            
            // Check domain restriction if set
            if (isset($license->restrictions['domain'])) {
                if (!$this->isDomainAllowed($requestDomain, $license->restrictions['domain'])) {
                    throw new LicenseValidationException(
                        'Invalid domain. Domain must match or be a subdomain of: ' . 
                        $license->restrictions['domain']
                    );
                }
            }
    
            // Check for existing activation by domain
            $existingDomainActivation = $license->activations()
                ->where('domain', $requestDomain)
                ->where('is_active', true)
                ->first();
    
            // Check for existing activation by device
            $existingDeviceActivation = $license->activations()
                ->where('device_identifier', $activationData['device_identifier'])
                ->first();
    
            // If domain is already activated on a different device
            if ($existingDomainActivation && 
                (!$existingDeviceActivation || 
                 $existingDomainActivation->id !== $existingDeviceActivation->id)) {
                throw new LicenseValidationException(
                    "Domain {$requestDomain} is already activated on another device"
                );
            }
    
            if ($existingDeviceActivation) {
                // Handle device reactivation
                if (!$existingDeviceActivation->is_active) {
                    // Check available seats before reactivation
                    if (!$license->hasAvailableSeats()) {
                        throw new LicenseValidationException('No available seats');
                    }
    
                    $existingDeviceActivation->update([
                        'is_active' => true,
                        'device_name' => $activationData['device_name'],
                        'hardware_hash' => $this->createHardwareHash($activationData['hardware']),
                        'domain' => $requestDomain,
                        'ip_address' => $activationData['ip_address'] ?? request()->ip(),
                        'metadata' => array_merge(
                            $existingDeviceActivation->metadata ?? [], 
                            $activationData['metadata'] ?? []
                        ),
                        'activated_at' => $timestamp,
                        'activated_by' => $currentUser,
                        'last_check_in' => $timestamp,
                        'next_check_in' => $timestamp->copy()->addDays(
                            $license->settings['check_in_interval'] ?? 7
                        ),
                        'deactivated_at' => null,
                        'deactivated_by' => null
                    ]);
    
                    $this->repository->logLicenseEvent($license, 'reactivated', [
                        'activation_id' => $existingDeviceActivation->id,
                        'device_identifier' => $existingDeviceActivation->device_identifier,
                        'domain' => $requestDomain,
                        'hardware_hash' => $existingDeviceActivation->hardware_hash,
                        'ip_address' => $existingDeviceActivation->ip_address,
                        'activated_by' => $currentUser,
                        'timestamp' => $timestamp->toIso8601String()
                    ]);
    
                    return [
                        'success' => true,
                        'activation_id' => $existingDeviceActivation->id,
                        'features' => $license->features,
                        'expires_at' => $license->valid_until,
                        'check_in_required_at' => $existingDeviceActivation->next_check_in,
                        'activated_by' => $currentUser,
                        'activated_at' => $timestamp->toIso8601String(),
                        'status' => 'reactivated',
                        'domain' => $requestDomain
                    ];
                }
    
                throw new LicenseValidationException('Device already activated for this license');
            }
    
            // Check available seats for new activation
            if (!$license->hasAvailableSeats()) {
                throw new LicenseValidationException('No available seats');
            }
    
            // Create new activation
            $activation = $license->activations()->create([
                'device_identifier' => $activationData['device_identifier'],
                'device_name' => $activationData['device_name'],
                'hardware_hash' => $this->createHardwareHash($activationData['hardware']),
                'domain' => $requestDomain,
                'ip_address' => $activationData['ip_address'] ?? request()->ip(),
                'metadata' => $activationData['metadata'] ?? [],
                'activated_at' => $timestamp,
                'activated_by' => $currentUser,
                'is_active' => true,
                'last_check_in' => $timestamp,
                'next_check_in' => $timestamp->copy()->addDays(
                    $license->settings['check_in_interval'] ?? 7
                )
            ]);
    
            $this->repository->logLicenseEvent($license, 'activated', [
                'activation_id' => $activation->id,
                'device_identifier' => $activation->device_identifier,
                'domain' => $requestDomain,
                'hardware_hash' => $activation->hardware_hash,
                'ip_address' => $activation->ip_address,
                'activated_by' => $currentUser,
                'timestamp' => $timestamp->toIso8601String()
            ]);
    
            return [
                'success' => true,
                'activation_id' => $activation->id,
                'features' => $license->features,
                'expires_at' => $license->valid_until,
                'check_in_required_at' => $activation->next_check_in,
                'activated_by' => $currentUser,
                'activated_at' => $timestamp->toIso8601String(),
                'status' => 'new_activation',
                'domain' => $requestDomain
            ];
        });
    }

    private function normalizeDomain(string $domain): string
    {
        // Remove protocol and www if present
        $domain = preg_replace('#^https?://(www\.)?#i', '', strtolower($domain));
        // Remove trailing slashes and whitespace
        return trim($domain, "/ \t\n\r\0\x0B");
    }

    private function isDomainAllowed(string $requestDomain, string $allowedDomain): bool
    {
        // Check if domains match exactly or if request domain is subdomain of allowed domain
        return $requestDomain === $allowedDomain || 
            (str_ends_with($requestDomain, '.' . $allowedDomain));
    }

    private function validateLicenseSignature(License $license): bool
    {
        try {
            $signatureInfo = $license->metadata['_signature'] ?? null;

            if (!$signatureInfo || !isset($signatureInfo['encrypted'])) {
                Log::error('Missing signature data for license: ' . $license->key);
                return false;
            }

            // Use the stored encrypted data for verification
            $encryptedData = $signatureInfo['encrypted'];

            return $this->encryption->verify(
                $encryptedData,
                $license->signature,
                $license->auth_key_id
            );

        } catch (\Exception $e) {
            Log::error('Error validating license signature: ' . $e->getMessage(), [
                'license_key' => $license->key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function deactivateLicense(string $licenseKey, string $deviceIdentifier): array
    {
        return DB::transaction(function () use ($licenseKey, $deviceIdentifier) {
            $timestamp = Carbon::now();
            $currentUser = Auth::check() ? Auth::user()->id : 'system';;

            $license = $this->repository->findByKey($licenseKey);

            if (!$license) {
                throw new LicenseValidationException('License not found');
            }

            $activation = $license->activations()
                ->where('device_identifier', $deviceIdentifier)
                ->where('is_active', true)
                ->first();

            if (!$activation) {
                throw new LicenseValidationException('No active device found with this identifier');
            }

            // Deactivate the device
            $activation->update([
                'is_active' => false,
                'deactivated_at' => $timestamp,
                'deactivated_by' => $currentUser
            ]);

            // Log deactivation
            $this->repository->logLicenseEvent($license, 'deactivated', [
                'activation_id' => $activation->id,
                'device_identifier' => $deviceIdentifier,
                'deactivated_by' => $currentUser,
                'timestamp' => $timestamp->toIso8601String()
            ]);

            return [
                'success' => true,
                'deactivated_at' => $timestamp->toIso8601String(),
                'deactivated_by' => $currentUser,
                'remaining_seats' => $license->getRemainingSeats()
            ];
        });
    }

    public function validateLicense(string $licenseKey, array $validationData): array
    {
        $timestamp = Carbon::now();
        $currentUser = Auth::check() ? Auth::user()->id : 'system';;

        $license = $this->repository->findByKey($licenseKey);

        if (!$license) {
            throw new LicenseValidationException('License not found');
        }

        if (!$this->validateLicenseSignature($license)) {
            throw new LicenseValidationException('Invalid license signature');
        }

        // Verify device activation
        $activation = $license->activations()
            ->where('device_identifier', $validationData['device_identifier'])
            ->where('is_active', true)
            ->first();

        if (!$activation) {
            throw new LicenseValidationException('Device not activated for this license');
        }

        // Check domain
        if (isset($license->restrictions['domain'])) {
            if (!$this->isValidDomain($validationData['domain'], $license->restrictions['domain'])) {
                throw new LicenseValidationException('Invalid domain');
            }
        }

        // Update check-in time
        $activation->update([
            'last_check_in' => $timestamp,
            'next_check_in' => $timestamp->copy()->addDays(
                $license->settings['check_in_interval'] ?? 7
            )
        ]);

        return [
            'valid' => true,
            'features' => $license->features,
            'expires_at' => $license->valid_until,
            'next_check_in' => $activation->next_check_in,
            'validated_at' => $timestamp->toIso8601String(),
            'validated_by' => $currentUser
        ];
    }

    private function generateLicenseKey(): string
    {
        do {
            $key = sprintf(
                'LICENSE-%s-%s-%s',
                date('Y'),
                strtoupper(Str::random(4)),
                strtoupper(Str::random(4))
            );
        } while ($this->repository->findByKey($key));

        return $key;
    }

    private function createHardwareHash(array $hardware): string
    {
        return hash('sha256', json_encode([
            'cpu' => $hardware['cpu_id'],
            'disk' => $hardware['disk_id'],
            'mac' => $hardware['mac_address']
        ]));
    }

    private function isValidDomain(string $requestDomain, string $allowedDomain): bool
    {
        // Remove protocol and www if present
        $requestDomain = preg_replace('#^https?://(www\\.)?#', '', strtolower($requestDomain));
        $allowedDomain = preg_replace('#^https?://(www\\.)?#', '', strtolower($allowedDomain));

        // Check if domains match or if request domain is subdomain of allowed domain
        return $requestDomain === $allowedDomain || 
               preg_match('/' . preg_quote($allowedDomain) . '$/', $requestDomain);
    }
}