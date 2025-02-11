<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use App\Services\EnvatoService;
use App\Http\Requests\License\{
    CreateLicenseRequest,
    ActivateLicenseRequest,
    ValidateLicenseRequest,
    DeactivateLicenseRequest,
    CheckInRequest,
    RenewLicenseRequest
};
use App\Repositories\LicenseRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LicenseController extends Controller
{
    private const TIMESTAMP = '2025-02-09 15:56:09';
    private const USER = 'maab16';

    public function __construct(
        private readonly LicenseService $licenseService,
        private readonly EnvatoService $envatoService,
        private readonly LicenseRepository $licenseRepository
    ) {}

    public function create(CreateLicenseRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Handle Envato purchase verification if needed
            if ($data['source'] === 'envato') {
                $envatoData = $this->envatoService->verifyPurchase($data['source_purchase_code']);
                $data['source_metadata'] = $envatoData;
            }

            $license = $this->licenseService->createLicense($data);

            return response()->json([
                'success' => true,
                'message' => 'License created successfully',
                'data' => [
                    'license_key' => $license->key,
                    'type' => $license->type,
                    'valid_until' => $license->valid_until?->toIso8601String(),
                    'status' => $license->status
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('License creation failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::TIMESTAMP,
                'user' => self::USER
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create license',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function activate(ActivateLicenseRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            $result = $this->licenseService->activateLicense(
                $data['license_key'],
                $data
            );

            return response()->json([
                'success' => true,
                'message' => 'License activated successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('License activation failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::TIMESTAMP,
                'user' => self::USER
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate license',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function validate(ValidateLicenseRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            $result = $this->licenseService->validateLicense(
                $data['license_key'],
                $data
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('License validation failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::TIMESTAMP
            ]);

            return response()->json([
                'success' => false,
                'message' => 'License validation failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function deactivate(DeactivateLicenseRequest $request): JsonResponse
    {
        try {
            
            $response = $this->licenseService->deactivateLicense(
                $request->license_key,
                $request->activation_token,
                $request->reason
            );
        
            return response()->json([
                'success' => true,
                'message' => 'License deactivated successfully',
                'data' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('License deactivation failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::TIMESTAMP
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate license',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function deactivateByDomain(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string',
            'domain' => 'required|string',
            'activation_token' => 'required|string'
        ]);

        try {
            $result = $this->licenseService->deactivateByDomain(
                $request->license_key,
                $request->domain,
                $request->activation_token
            );

            return response()->json([
                'success' => true,
                'message' => 'Domain deactivated successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate domain',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function deactivateEntireLicense(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string',
            'reason' => 'nullable|string'
        ]);

        try {
            $result = $this->licenseService->deactivateEntireLicense(
                $request->license_key,
                $request->reason
            );

            return response()->json([
                'success' => true,
                'message' => 'License deactivated successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate license',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function bulkDeactivate(DeactivateLicenseRequest $request): JsonResponse
    {
        try {
            $response = $this->licenseService->bulkDeactivate(
                $request->license_key,
                $request->filters ?? []
            );
        
            return response()->json([
                'success' => true,
                'message' => 'Licenses deactivated successfully',
                'data' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('License deactivation failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::TIMESTAMP
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate license',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function checkIn(CheckInRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            $result = $this->licenseService->checkIn(
                $data['license_key'],
                $data
            );

            return response()->json([
                'success' => true,
                'message' => 'Check-in successful',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('License check-in failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::TIMESTAMP
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Check-in failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function renew(RenewLicenseRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            $license = $this->licenseRepository->findByKey($data['license_key']);
            if (!$license) {
                return response()->json([
                    'success' => false,
                    'message' => 'License not found'
                ], 404);
            }

            $renewedLicense = $this->licenseService->renewLicense($license, $data);

            return response()->json([
                'success' => true,
                'message' => 'License renewed successfully',
                'data' => [
                    'valid_until' => $renewedLicense->valid_until?->toIso8601String(),
                    'status' => $renewedLicense->status,
                    'seats' => $renewedLicense->purchased_seats,
                    'activated_seats' => $renewedLicense->activated_seats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('License renewal failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::TIMESTAMP,
                'user' => self::USER
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to renew license',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            $licenseKey = $request->input('license_key');
            if (!$licenseKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'License key is required'
                ], 400);
            }

            $license = $this->licenseRepository->findByKey($licenseKey);
            if (!$license) {
                return response()->json([
                    'success' => false,
                    'message' => 'License not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $license->status,
                    'type' => $license->type,
                    'valid_until' => $license->valid_until?->toIso8601String(),
                    'purchased_seats' => $license->purchased_seats,
                    'activated_seats' => $license->activated_seats,
                    'active_domains' => $license->domains()
                        ->where('is_active', true)
                        ->pluck('domain'),
                    'last_check_in' => $license->last_check_in?->toIso8601String(),
                    'next_check_in' => $license->next_check_in?->toIso8601String()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('License status check failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::TIMESTAMP,
                'user' => self::USER
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check license status',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function verifyEnvato(Request $request): JsonResponse
    {
        try {
            $purchaseCode = $request->input('purchase_code');
            if (!$purchaseCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase code is required'
                ], 400);
            }

            $envatoData = $this->envatoService->verifyPurchase($purchaseCode);

            return response()->json([
                'success' => true,
                'data' => $envatoData
            ]);

        } catch (\Exception $e) {
            Log::error('Envato verification failed', [
                'error' => $e->getMessage(),
                'timestamp' => self::TIMESTAMP
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify Envato purchase',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}