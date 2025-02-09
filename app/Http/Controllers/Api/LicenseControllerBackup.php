<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use App\Http\Requests\LicenseActivationRequest;
use App\Http\Requests\LicenseValidationRequest;
use App\Http\Requests\LicenseDeactivationRequest;
use Illuminate\Http\JsonResponse;

class LicenseControllerBackup extends Controller
{
    public function __construct(
        private readonly LicenseService $licenseService
    ) {}

    public function activate(LicenseActivationRequest $request): JsonResponse
    {
        try {
            $result = $this->licenseService->activateLicense(
                $request->input('license_key'),
                $request->validated()
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function validate(LicenseValidationRequest $request): JsonResponse
    {
        try {
            $result = $this->licenseService->validateLicense(
                $request->input('license_key'),
                $request->validated()
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function deactivate(LicenseDeactivationRequest $request): JsonResponse
    {
        try {
            $result = $this->licenseService->deactivateLicense(
                $request->input('license_key'),
                $request->input('device_identifier')
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }
}