<?php

namespace App\Http\Controllers\API;

use App\Exceptions\EnvatoActivationException;
use App\Models\License;
use App\Services\Envato\EnvatoLicenseService;
use App\Services\Envato\EnvatoActivationService;
use App\Http\Requests\Envato\{
    ActivateEnvatoPurchaseRequest,
    ConvertPurchaseRequest,
    VerifyPurchaseRequest,
    ActivateLicenseRequest,
    DeactivateDomainRequest,
    VerifyDomainRequest
};
use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EnvatoLicenseController extends Controller
{
    private const CURRENT_TIME = '2025-02-11 05:54:40';
    private const CURRENT_USER = 'maab16';

    public function __construct(
        private readonly EnvatoLicenseService $licenseService,
        private readonly EnvatoActivationService $activationService
    ) {}

    public function convert(ConvertPurchaseRequest $request): JsonResponse
    {
        try {
            Log::info('Starting Envato purchase conversion', [
                'purchase_code' => $request->purchase_code,
                'item_id' => $request->item_id,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            $license = $this->licenseService->convertToLicense(
                $request->purchase_code,
                $request->item_id
            );

            return response()->json([
                'success' => true,
                'message' => 'License created successfully',
                'data' => [
                    'license_id' => $license->id,
                    'status' => $license->status,
                    'valid_until' => $license->valid_until,
                    'features' => $license->metadata['original_features'],
                    'envato' => [
                        'purchase_code' => $license->metadata['envato_purchase_code'],
                        'buyer' => $license->metadata['envato_buyer'],
                        'license_type' => str_replace('envato_', '', $license->license_type)
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Purchase conversion failed', [
                'purchase_code' => $request->purchase_code,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Purchase conversion failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function verify(VerifyPurchaseRequest $request): JsonResponse
    {
        try {
            Log::info('Starting purchase code verification', [
                'purchase_code' => $request->purchase_code,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            $purchaseData = $this->licenseService->verifyPurchaseCode(
                $request->purchase_code,
                $request->item_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Purchase code verified successfully',
                'data' => [
                    'is_valid' => true,
                    'license_type' => $purchaseData['license'],
                    'purchase_date' => $purchaseData['sold_at'],
                    'support_until' => $purchaseData['supported_until'],
                    'buyer' => $purchaseData['buyer']
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Purchase verification failed', [
                'purchase_code' => $request->purchase_code,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Purchase verification failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function activate(ActivateLicenseRequest $request, License $license): JsonResponse
    {
        try {
            Log::info('Starting license activation', [
                'license_id' => $license->id,
                'domain' => $request->domain,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            $result = $this->activationService->activateEnvatoLicense($license, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'License activated successfully',
                'data' => $result
            ]);

        } catch (EnvatoActivationException $e) {
            Log::error('License activation failed', [
                'license_id' => $license->id,
                'domain' => $request->domain,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return response()->json([
                'success' => false,
                'message' => 'License activation failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function deactivate(DeactivateDomainRequest $request, License $license): JsonResponse
    {
        try {
            Log::info('Starting domain deactivation', [
                'license_id' => $license->id,
                'domain' => $request->domain,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            $this->activationService->deactivateEnvatoDomain($license, $request->domain);

            return response()->json([
                'success' => true,
                'message' => 'Domain deactivated successfully',
                'data' => [
                    'domain' => $request->domain,
                    'deactivated_at' => self::CURRENT_TIME,
                    'status' => 'deactivated'
                ]
            ]);

        } catch (EnvatoActivationException $e) {
            Log::error('Domain deactivation failed', [
                'license_id' => $license->id,
                'domain' => $request->domain,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Domain deactivation failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function show(License $license): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'license_id' => $license->id,
                'status' => $license->status,
                'type' => $license->license_type,
                'valid_until' => $license->valid_until,
                'seats' => [
                    'total' => $license->total_seats,
                    'activated' => $license->activated_seats,
                    'remaining' => $license->total_seats === -1 ? 
                        'unlimited' : 
                        ($license->total_seats - $license->activated_seats)
                ],
                'features' => $license->metadata['original_features'],
                'envato' => [
                    'purchase_code' => $license->metadata['envato_purchase_code'],
                    'buyer' => $license->metadata['envato_buyer'],
                    'license_type' => str_replace('envato_', '', $license->license_type),
                    'support_until' => $license->valid_until
                ],
                'active_domains' => $license->domains()
                    ->where('is_active', true)
                    ->get()
                    ->map(function ($domain) {
                        return [
                            'domain' => $domain->domain,
                            'activated_at' => $domain->activated_at,
                            'is_production' => !$this->activationService->isLocalDomain($domain->domain)
                        ];
                    })
            ]
        ]);
    }

    public function domains(License $license): JsonResponse
    {
        $domains = $license->domains()
            ->where('is_active', true)
            ->get()
            ->partition(function ($domain) {
                return !$this->activationService->isLocalDomain($domain->domain);
            });

        return response()->json([
            'success' => true,
            'data' => [
                'production_domains' => $domains[0]->map(function ($domain) {
                    return [
                        'domain' => $domain->domain,
                        'activated_at' => $domain->activated_at,
                        'last_verified_at' => $domain->last_verified_at,
                        'status' => 'active'
                    ];
                }),
                'local_domains' => $domains[1]->map(function ($domain) {
                    return [
                        'domain' => $domain->domain,
                        'activated_at' => $domain->activated_at,
                        'last_verified_at' => $domain->last_verified_at,
                        'status' => 'active'
                    ];
                })
            ]
        ]);
    }

    public function verifyDomain(VerifyDomainRequest $request, License $license): JsonResponse
    {
        try {
            Log::info('Starting domain verification', [
                'license_id' => $license->id,
                'domain' => $request->domain,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            $domain = $license->domains()
                ->where('domain', $request->domain)
                ->where('is_active', true)
                ->firstOrFail();

            // Verify activation token
            if ($domain->activation->activation_token !== $request->activation_token) {
                throw new EnvatoActivationException('Invalid activation token');
            }

            $domain->update([
                'last_verified_at' => self::CURRENT_TIME,
                'updated_at' => self::CURRENT_TIME,
                'updated_by' => self::CURRENT_USER
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Domain verified successfully',
                'data' => [
                    'domain' => $domain->domain,
                    'status' => 'active',
                    'is_valid' => true,
                    'activated_at' => $domain->activated_at,
                    'last_verified_at' => $domain->last_verified_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Domain verification failed', [
                'license_id' => $license->id,
                'domain' => $request->domain,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Domain verification failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function activateWithPurchaseCode(ActivateEnvatoPurchaseRequest $request): JsonResponse
    {
        try {
            Log::info('Starting Envato purchase activation', [
                'purchase_code' => $request->purchase_code,
                'domain' => $request->domain,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            $result = $this->activationService->activateWithPurchaseCode(
                $request->purchase_code,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'License activated successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Envato purchase activation failed', [
                'purchase_code' => $request->purchase_code,
                'domain' => $request->domain,
                'error' => $e->getMessage(),
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            return response()->json([
                'success' => false,
                'message' => 'License activation failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}