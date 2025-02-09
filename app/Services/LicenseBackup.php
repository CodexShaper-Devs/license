<?php

namespace App\Services;

use App\DataTransferObjects\LicenseSignatureData;
use App\Events\LicenseActivated;
use App\Events\LicenseDeactivated;
use App\Events\LicenseRenewed;
use App\Events\LicenseSuspended;
use App\Events\LicenseTransferred;
use App\Models\License;
use App\Repositories\LicenseRepository;
use App\Events\LicenseValidated;
use App\Exceptions\LicenseActivationException;
use App\Exceptions\LicenseValidationException;
use App\ValueObjects\LicenseKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LicenseBackup
{
    public function __construct(
        private readonly LicenseRepository $repository,
        private readonly EncryptionService $encryption,
        private readonly HardwareValidationService $hardwareValidator
    ) {}

    public function createLicense(array $data): License
    {
        return DB::transaction(function () use ($data) {
            // Generate license key and encryption key
            $data['key'] = (new LicenseKey())->toString();
            $data['encryption_key_id'] = bin2hex(random_bytes(16));

            // Create signature data
            $signatureData = LicenseSignatureData::fromArray($data);

            // Encrypt and sign the data
            $encryptedData = $this->encryption->encrypt(
                $this->getLicenseDataForSignature($signatureData),
                $data['encryption_key_id']
            );
            
            $data['signature'] = $this->encryption->sign(
                $encryptedData,
                $data['encryption_key_id']
            );

            // Create the license
            $license = $this->repository->create($data);

            // Log the creation
            $this->repository->logLicenseEvent($license, 'created', [
                'encryption_key_id' => $data['encryption_key_id'],
                'features' => $data['features'] ?? [],
                'restrictions' => $data['restrictions'] ?? null,
            ]);

            return $license;
        });
    }

    protected function getLicenseDataForSignature(LicenseSignatureData $data): array
    {
        return [
            'key' => $data->key,
            'user_id' => $data->userId,
            'product_id' => $data->productId,
            'type' => $data->type,
            'seats' => $data->seats,
            'valid_from' => $data->validFrom->toIso8601String(),
            'valid_until' => $data->validUntil?->toIso8601String(),
            'features' => $data->features,
            'restrictions' => $data->restrictions,
        ];
    }

    public function verifyLicenseSignature(License $license): bool
    {
        if (!$license->signature || !$license->encryption_key_id) {
            return false;
        }

        $signatureData = LicenseSignatureData::fromArray($license->toArray());
        $dataToVerify = $this->getLicenseDataForSignature($signatureData);
        
        try {
            return $this->encryption->verify(
                $dataToVerify,
                $license->signature,
                $license->encryption_key_id
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    public function validateLicense(string $key, array $context): array
    {
        $cacheKey = "license_validation:{$key}";
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($key, $context) {
            try {
                $license = $this->repository->findByKey($key);
                
                if (!$license) {
                    throw new \Exception('License not found');
                }

                if (!$this->verifyLicenseSignature($license)) {
                    throw new \Exception('Invalid license signature');
                }

                if (!$license->isValid()) {
                    throw new \Exception('License is not valid');
                }

                if (!$license->validateRestrictions($context)) {
                    throw new \Exception('License restrictions not met');
                }

                if (!$this->hardwareValidator->validate($license, $context['hardware'] ?? [])) {
                    throw new \Exception('Hardware validation failed');
                }

                $this->updateValidationStats($license, $context);
                event(new LicenseValidated($license, $context));

                return [
                    'valid' => true,
                    'features' => $license->features,
                    'expires' => $license->valid_until,
                ];

            } catch (\Exception $e) {
                Log::warning('License validation failed', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                    'context' => $context,
                ]);

                return [
                    'valid' => false,
                    'error' => $e->getMessage(),
                ];
            }
        });
    }

    protected function updateValidationStats(License $license, array $context): void
    {
        $license->update([
            'last_validated_at' => now(),
            'last_validated_ip' => $context['ip'] ?? request()->ip(),
            'validation_count' => DB::raw('validation_count + 1'),
        ]);
    }

    public function renewLicense(License $license, \DateTime $newExpiration): bool
    {
        return DB::transaction(function () use ($license, $newExpiration) {
            $updated = $license->update([
                'valid_until' => $newExpiration,
                'is_active' => true
            ]);

            if ($updated) {
                event(new LicenseRenewed($license));
                $this->repository->logLicenseEvent($license, 'renewed', [
                    'previous_expiration' => $license->getOriginal('valid_until'),
                    'new_expiration' => $newExpiration
                ]);
            }

            return $updated;
        });
    }

    public function suspendLicense(License $license, string $reason): bool
    {
        return DB::transaction(function () use ($license, $reason) {
            $updated = $license->update(['is_active' => false]);

            if ($updated) {
                event(new LicenseSuspended($license, $reason));
                $this->repository->logLicenseEvent($license, 'suspended', [
                    'reason' => $reason
                ]);
            }

            return $updated;
        });
    }

    public function transferLicense(License $license, int $newUserId): bool
    {
        return DB::transaction(function () use ($license, $newUserId) {
            $oldUserId = $license->user_id;
            
            $updated = $license->update([
                'user_id' => $newUserId,
                'transfer_date' => now()
            ]);

            if ($updated) {
                event(new LicenseTransferred($license, $oldUserId, $newUserId));
                $this->repository->logLicenseEvent($license, 'transferred', [
                    'from_user_id' => $oldUserId,
                    'to_user_id' => $newUserId
                ]);
            }

            return $updated;
        });
    }

    public function activateLicense(string $licenseKey, array $activationData): array
    {
        try {
            DB::beginTransaction();

            $license = $this->repository->findByKey($licenseKey);
            if (!$license) {
                throw new LicenseActivationException('License not found');
            }

            if (!$license->isValid()) {
                throw new LicenseActivationException('License is not valid');
            }

            if (!$license->hasAvailableSeats()) {
                throw new LicenseActivationException('No available seats');
            }

            // Validate hardware
            $hardwareHash = $this->hardwareValidator->generateHardwareHash($activationData['hardware'] ?? []);
            $existingActivation = $license->activations()
                ->where('hardware_hash', $hardwareHash)
                ->first();

            if ($existingActivation && $existingActivation->is_active) {
                throw new LicenseActivationException('Device already activated');
            }

            // Create activation
            $activation = $license->activations()->create([
                'device_identifier' => $activationData['device_identifier'],
                'device_name' => $activationData['device_name'],
                'hardware_hash' => $hardwareHash,
                'ip_address' => $activationData['ip_address'] ?? request()->ip(),
                'domain' => $activationData['domain'] ?? null,
                'last_check_in' => now(),
                'metadata' => $activationData['metadata'] ?? null,
                'is_active' => true
            ]);

            // Log the activation
            $this->repository->logLicenseEvent($license, 'activated', [
                'device_identifier' => $activation->device_identifier,
                'hardware_hash' => $hardwareHash,
                'ip_address' => $activation->ip_address,
            ]);

            event(new LicenseActivated($license, $activation));

            DB::commit();

            return [
                'success' => true,
                'activation_id' => $activation->id,
                'features' => $license->features,
                'expires_at' => $license->valid_until,
                'check_in_required_at' => now()->addDays(
                    config('licensing.activation.check_in_interval', 7)
                ),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('License activation failed', [
                'license_key' => $licenseKey,
                'error' => $e->getMessage(),
                'context' => $activationData,
            ]);

            throw new LicenseActivationException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function deactivateLicense(string $licenseKey, string $deviceIdentifier): array
    {
        try {
            DB::beginTransaction();

            $license = $this->repository->findByKey($licenseKey);
            if (!$license) {
                throw new LicenseActivationException('License not found');
            }

            $activation = $license->activations()
                ->where('device_identifier', $deviceIdentifier)
                ->where('is_active', true)
                ->first();

            if (!$activation) {
                throw new LicenseActivationException('No active device found');
            }

            // Deactivate the device
            $activation->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivation_reason' => 'user_requested'
            ]);

            // Log the deactivation
            $this->repository->logLicenseEvent($license, 'deactivated', [
                'device_identifier' => $deviceIdentifier,
                'hardware_hash' => $activation->hardware_hash,
                'ip_address' => request()->ip(),
            ]);

            event(new LicenseDeactivated($license, $activation));

            DB::commit();

            return [
                'success' => true,
                'message' => 'License deactivated successfully',
                'remaining_seats' => $license->seats - $license->activations()
                    ->where('is_active', true)
                    ->count(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('License deactivation failed', [
                'license_key' => $licenseKey,
                'device_identifier' => $deviceIdentifier,
                'error' => $e->getMessage(),
            ]);

            throw new LicenseActivationException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function validateActivation(string $licenseKey, string $deviceIdentifier): array
    {
        $cacheKey = "license_activation:{$licenseKey}:{$deviceIdentifier}";
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($licenseKey, $deviceIdentifier) {
            $license = $this->repository->findByKey($licenseKey);
            if (!$license) {
                throw new LicenseValidationException('License not found');
            }

            $activation = $license->activations()
                ->where('device_identifier', $deviceIdentifier)
                ->where('is_active', true)
                ->first();

            if (!$activation) {
                throw new LicenseValidationException('Device not activated');
            }

            if (!$this->isActivationValid($activation)) {
                throw new LicenseValidationException('Activation expired');
            }

            // Update last check-in time
            $activation->update([
                'last_check_in' => now(),
                'check_in_count' => DB::raw('check_in_count + 1')
            ]);

            return [
                'valid' => true,
                'features' => $license->features,
                'expires_at' => $license->valid_until,
                'next_check_in' => now()->addDays(
                    config('licensing.activation.check_in_interval', 7)
                ),
            ];
        });
    }

    protected function isActivationValid($activation): bool
    {
        if (!$activation->is_active) {
            return false;
        }

        $maxCheckInInterval = config('licensing.activation.max_check_in_interval', 30);
        if ($activation->last_check_in->diffInDays(now()) > $maxCheckInInterval) {
            $activation->update(['is_active' => false]);
            return false;
        }

        return true;
    }
}