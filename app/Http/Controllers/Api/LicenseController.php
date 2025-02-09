<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateLicenseRequest;
use App\Http\Requests\ActivateLicenseRequest;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LicenseController extends Controller
{
    private const TIMESTAMP = '2025-02-09 07:19:55';
    private const USER = 'maab16';

    public function __construct(
        private readonly LicenseService $licenseService
    ) {}

    public function create(CreateLicenseRequest $request): JsonResponse
    {
        try {
            $license = $this->licenseService->createLicense(array_merge(
                $request->validated(),
                ['source' => 'custom']
            ));

            return response()->json([
                'success' => true,
                'message' => 'License created successfully',
                'data' => $license,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'License created successfully',
                'data' => [
                    'license_key' => $license->key,
                    'type' => $license->type,
                    'valid_from' => $license->valid_from->toIso8601String(),
                    'valid_until' => $license->valid_until?->toIso8601String(),
                    'features' => $license->features,
                    'created_at' => self::TIMESTAMP
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create license',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function activate(Request $request): JsonResponse
    {
        try {
            // return response()->json($request->validated());
            $result = $this->licenseService->activateLicense(
                $request->input('license_key'),
                $request->except(['license_key'])
            );

            return response()->json([
                'success' => true,
                'message' => 'License activated successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate license',
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

    public function validate(Request $request): JsonResponse
    {
        try {
            $result = $this->licenseService->validateLicense(
                $request->input('license_key'),
                $request->except(['license_key'])
            );

            return response()->json([
                'success' => true,
                'message' => 'License is valid',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'License validation failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function status(string $key): JsonResponse
    {
        try {
            $result = $this->licenseService->validateLicense($key);

            return response()->json([
                'success' => true,
                'message' => 'License status retrieved successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve license status',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function renew(Request $request): JsonResponse
    {
        try {
            // $result = $this->licenseService->renewLicense(
            //     $request->input('license_key'),
            //     $request->input('valid_until')
            // );

            return response()->json([
                'success' => true,
                'message' => 'License renewed successfully',
                'data' => []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to renew license',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}