<?php

use App\Http\Controllers\API\EnvatoLicenseController;
use App\Http\Controllers\API\LicenseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Public routes (no authentication required).
    Route::post('licenses/validate', [LicenseController::class, 'validate']);
    Route::post('licenses/check-in', [LicenseController::class, 'checkIn']);
    Route::post('licenses/verify-envato', [LicenseController::class, 'verifyEnvato']);

    // Protected routes (require authentication)
    // Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('licenses')->group(function () {
            Route::post('/', [LicenseController::class, 'create']);
            Route::post('activate', [LicenseController::class, 'activate']);
            Route::post('deactivate', [LicenseController::class, 'deactivate']);
            Route::post('deactivate-by-domain', [LicenseController::class, 'deactivateByDomain']);
            Route::post('renew', [LicenseController::class, 'renew']);
        });
    // });
});

Route::prefix('v1')->group(function () {
    // Envato License Management Routes
    Route::prefix('envato')->group(function () {
        // Convert Envato Purchase to License
        // Route::post('convert', [EnvatoLicenseController::class, 'convert']);
        Route::post('activate', [EnvatoLicenseController::class, 'activateWithPurchaseCode']);
        
        // Verify Envato Purchase
        Route::post('verify', [EnvatoLicenseController::class, 'verify']);
        
        // License Management
        Route::prefix('licenses')->group(function () {
            // Activate License
            Route::post('{license}/activate', [EnvatoLicenseController::class, 'activate']);
            
            // Deactivate Domain
            Route::post('{license}/deactivate', [EnvatoLicenseController::class, 'deactivate']);
            
            // Get License Details
            Route::get('{license}', [EnvatoLicenseController::class, 'show']);
            
            // List Active Domains
            Route::get('{license}/domains', [EnvatoLicenseController::class, 'domains']);
            
            // Verify Domain Status
            Route::post('{license}/verify-domain', [EnvatoLicenseController::class, 'verifyDomain']);
        });
    });
});