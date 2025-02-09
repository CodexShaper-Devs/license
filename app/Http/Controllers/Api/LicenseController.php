<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use App\Exceptions\LicenseValidationException;

class LicenseController extends Controller
{
    public function __construct(
        private readonly LicenseService $licenseService
    ) {}

    public function create(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'product_id' => 'required|exists:products,id',
                'type' => 'required|in:subscription,perpetual',
                'status' => 'required|in:active,inactive,suspended',
                'seats' => 'required|integer|min:1',
                'valid_from' => 'required|date',
                'valid_until' => 'required|date|after:valid_from',
                'features' => 'required|array',
                'restrictions' => 'array',
                'metadata' => 'nullable|array',
                'settings' => 'nullable|array',
            ]);

            $license = $this->licenseService->createLicense($validated);

            return response()->json([
                'success' => true,
                'message' => 'License created successfully',
                'data' => [
                    'license_key' => $license->key,
                    'valid_from' => $license->valid_from,
                    'valid_until' => $license->valid_until,
                    'features' => $license->features,
                    'restrictions' => $license->restrictions,
                    'seats' => $license->seats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function activate(Request $request)
    {
        try {
            $validated = $request->validate([
                'license_key' => 'required|string',
                'device_identifier' => 'required|string',
                'device_name' => 'required|string',
                'hardware' => 'required|array',
                'hardware.cpu_id' => 'required|string',
                'hardware.disk_id' => 'required|string',
                'hardware.mac_address' => 'required|string',
                'domain' => 'required|string',
                'metadata' => 'nullable|array'
            ]);

            $result = $this->licenseService->activateLicense(
                $validated['license_key'],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'License activated successfully',
                'data' => $result
            ]);

        } catch (LicenseValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function deactivate(Request $request)
    {
        try {
            $validated = $request->validate([
                'license_key' => 'required|string',
                'device_identifier' => 'required|string'
            ]);

            $result = $this->licenseService->deactivateLicense(
                $validated['license_key'],
                $validated['device_identifier']
            );

            return response()->json([
                'success' => true,
                'message' => 'License deactivated successfully',
                'data' => $result
            ]);

        } catch (LicenseValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function validate(Request $request)
    {
        try {
            $validated = $request->validate([
                'license_key' => 'required|string',
                'device_identifier' => 'required|string',
                'domain' => 'required|string'
            ]);

            $result = $this->licenseService->validateLicense(
                $validated['license_key'],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'License is valid',
                'data' => $result
            ]);

        } catch (LicenseValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}