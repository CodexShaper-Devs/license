<?php

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