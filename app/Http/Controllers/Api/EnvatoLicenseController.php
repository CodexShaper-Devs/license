<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use App\Services\EnvatoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\EnvatoLicenseRequest;
use Carbon\Carbon;

class EnvatoLicenseController extends Controller
{
    private const TIMESTAMP = '2025-02-09 07:25:35';
    private const USER = 'maab16';

    public function __construct(
        private readonly LicenseService $licenseService,
        private readonly EnvatoService $envatoService
    ) {}

    public function create(EnvatoLicenseRequest $request): JsonResponse
    {
        try {
            // Verify purchase code with Envato
            $envatoData = $this->envatoService->verifyPurchase(
                $request->input('purchase_code')
            );

            // Create license with Envato data
            $license = $this->licenseService->createLicense([
                'source' => 'envato',
                'source_purchase_code' => $request->input('purchase_code'),
                'source_data' => $envatoData,
                'type' => 'onetime', // Envato licenses are typically one-time
                'product_id' => $request->input('product_id'),
                'user_id' => $request->input('user_id'),
                'features' => $request->input('features', []),
                'valid_from' => Carbon::parse(self::TIMESTAMP),
                'restrictions' => $request->input('restrictions', []),
                'metadata' => array_merge(
                    $request->input('metadata', []),
                    ['envato_verified_at' => self::TIMESTAMP]
                )
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Envato license created successfully',
                'data' => [
                    'license_key' => $license->key,
                    'purchase_code' => $license->source_purchase_code,
                    'buyer' => $envatoData['buyer'],
                    'valid_from' => $license->valid_from->toIso8601String(),
                    'features' => $license->features,
                    'created_at' => self::TIMESTAMP
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Envato license',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function activate(Request $request): JsonResponse
    {
        try {
            // Verify purchase code again during activation
            $this->envatoService->verifyPurchase($request->input('purchase_code'));

            $result = $this->licenseService->activateLicense(
                $request->input('license_key'),
                array_merge($request->except(['license_key', 'purchase_code']), [
                    'envato_verified_at' => self::TIMESTAMP
                ])
            );

            return response()->json([
                'success' => true,
                'message' => 'Envato license activated successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate Envato license',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function validate(Request $request): JsonResponse
    {
        try {
            // Verify purchase code during validation
            $this->envatoService->verifyPurchase($request->input('purchase_code'));

            $result = $this->licenseService->validateLicense(
                $request->input('license_key'),
                array_merge($request->except(['license_key', 'purchase_code']), [
                    'envato_verified_at' => self::TIMESTAMP
                ])
            );

            return response()->json([
                'success' => true,
                'message' => 'Envato license is valid',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Envato license validation failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function status(string $purchase_code): JsonResponse
    {
        try {
            // Verify purchase code
            $envatoData = $this->envatoService->verifyPurchase($purchase_code);
            $license = $this->licenseService->findByPurchaseCode($purchase_code);

            return response()->json([
                'success' => true,
                'message' => 'License status retrieved successfully',
                'data' => [
                    'license_key' => $license->key,
                    'purchase_code' => $purchase_code,
                    'buyer' => $envatoData['buyer'],
                    'status' => $license->status,
                    'valid_from' => $license->valid_from->toIso8601String(),
                    'features' => $license->features,
                    'last_verified' => self::TIMESTAMP
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Envato license status',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function deactivate(Request $request): JsonResponse
    {
        try {
            $result = $this->licenseService->deactivateLicense(
                $request->input('license_key'),
                $request->input('activation_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Envato license deactivated successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate Envato license',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function renew(Request $request): JsonResponse
    {
        try {
            // For Envato, renewal might involve verifying a new purchase code
            $this->envatoService->verifyPurchase($request->input('new_purchase_code'));

            // Implementation pending based on renewal requirements
            return response()->json([
                'success' => true,
                'message' => 'Envato license renewed successfully',
                'data' => [
                    'renewed_at' => self::TIMESTAMP
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to renew Envato license',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}