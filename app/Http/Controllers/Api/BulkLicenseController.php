<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use App\Services\EnvatoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\BulkLicenseRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BulkLicenseController extends Controller
{
    private const TIMESTAMP = '2025-02-09 07:25:35';
    private const USER = 'maab16';
    private const MAX_BULK_SIZE = 100;

    public function __construct(
        private readonly LicenseService $licenseService,
        private readonly EnvatoService $envatoService
    ) {}

    public function create(BulkLicenseRequest $request): JsonResponse
    {
        try {
            $licenses = collect($request->input('licenses'));

            if ($licenses->count() > self::MAX_BULK_SIZE) {
                throw new \Exception("Maximum bulk size exceeded. Limit is " . self::MAX_BULK_SIZE);
            }

            $results = DB::transaction(function () use ($licenses) {
                return $licenses->map(function ($licenseData) {
                    try {
                        // Handle both custom and Envato licenses
                        if ($licenseData['source'] === 'envato') {
                            $this->envatoService->verifyPurchase($licenseData['source_purchase_code']);
                        }

                        $license = $this->licenseService->createLicense(array_merge(
                            $licenseData,
                            ['created_at' => self::TIMESTAMP]
                        ));

                        return [
                            'success' => true,
                            'license_key' => $license->key,
                            'source' => $license->source,
                            'source_reference' => $license->source_purchase_code ?? null
                        ];
                    } catch (\Exception $e) {
                        return [
                            'success' => false,
                            'error' => $e->getMessage(),
                            'source_reference' => $licenseData['source_purchase_code'] ?? null
                        ];
                    }
                });
            });

            $successCount = $results->where('success', true)->count();
            $failureCount = $results->where('success', false)->count();

            return response()->json([
                'success' => true,
                'message' => "Processed {$successCount} licenses successfully, {$failureCount} failed",
                'data' => [
                    'total' => $results->count(),
                    'successful' => $successCount,
                    'failed' => $failureCount,
                    'results' => $results,
                    'processed_at' => self::TIMESTAMP
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk license creation failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function validate(Request $request): JsonResponse
    {
        try {
            $licenses = collect($request->input('licenses'));

            if ($licenses->count() > self::MAX_BULK_SIZE) {
                throw new \Exception("Maximum bulk size exceeded. Limit is " . self::MAX_BULK_SIZE);
            }

            $results = $licenses->map(function ($licenseData) {
                try {
                    $result = $this->licenseService->validateLicense(
                        $licenseData['license_key'],
                        $licenseData['validation_data'] ?? []
                    );

                    return [
                        'success' => true,
                        'license_key' => $licenseData['license_key'],
                        'valid' => true,
                        'data' => $result
                    ];
                } catch (\Exception $e) {
                    return [
                        'success' => false,
                        'license_key' => $licenseData['license_key'],
                        'valid' => false,
                        'error' => $e->getMessage()
                    ];
                }
            });

            $validCount = $results->where('valid', true)->count();
            $invalidCount = $results->where('valid', false)->count();

            return response()->json([
                'success' => true,
                'message' => "Validated {$validCount} licenses successfully, {$invalidCount} invalid",
                'data' => [
                    'total' => $results->count(),
                    'valid' => $validCount,
                    'invalid' => $invalidCount,
                    'results' => $results,
                    'validated_at' => self::TIMESTAMP
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk license validation failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function renew(Request $request): JsonResponse
    {
        try {
            $licenses = collect($request->input('licenses'));

            if ($licenses->count() > self::MAX_BULK_SIZE) {
                throw new \Exception("Maximum bulk size exceeded. Limit is " . self::MAX_BULK_SIZE);
            }

            $results = DB::transaction(function () use ($licenses) {
                return $licenses->map(function ($licenseData) {
                    try {
                        // Implementation pending based on renewal requirements
                        return [
                            'success' => true,
                            'license_key' => $licenseData['license_key'],
                            'renewed_until' => Carbon::parse(self::TIMESTAMP)
                                ->addDays($licenseData['days'] ?? 365)
                                ->toIso8601String()
                        ];
                    } catch (\Exception $e) {
                        return [
                            'success' => false,
                            'license_key' => $licenseData['license_key'],
                            'error' => $e->getMessage()
                        ];
                    }
                });
            });

            $successCount = $results->where('success', true)->count();
            $failureCount = $results->where('success', false)->count();

            return response()->json([
                'success' => true,
                'message' => "Renewed {$successCount} licenses successfully, {$failureCount} failed",
                'data' => [
                    'total' => $results->count(),
                    'successful' => $successCount,
                    'failed' => $failureCount,
                    'results' => $results,
                    'processed_at' => self::TIMESTAMP
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk license renewal failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}