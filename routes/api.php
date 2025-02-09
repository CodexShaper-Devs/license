<?php

// use App\Http\Controllers\Api\LicenseController;
// use Illuminate\Support\Facades\Route;

// Route::prefix('v1/licenses')->group(function () {
//     Route::post('create', [LicenseController::class, 'create']);
//     Route::post('activate', [LicenseController::class, 'activate']);
//     Route::post('validate', [LicenseController::class, 'validate']);
//     Route::post('deactivate', [LicenseController::class, 'deactivate']);
// });

use App\Http\Controllers\API\LicenseController;
use App\Http\Controllers\API\EnvatoLicenseController;
use App\Http\Controllers\API\BulkLicenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Custom License Routes
    Route::prefix('licenses')->group(function () {
        Route::post('/', [LicenseController::class, 'create'])->name('licenses.create');
        Route::post('activate', [LicenseController::class, 'activate'])->name('licenses.activate');
        Route::post('deactivate', [LicenseController::class, 'deactivate'])->name('licenses.deactivate');
        Route::post('validate', [LicenseController::class, 'validate'])->name('licenses.validate');
        Route::get('status/{key}', [LicenseController::class, 'status'])->name('licenses.status');
        Route::post('renew', [LicenseController::class, 'renew'])->name('licenses.renew');
    });

    // Envato License Routes
    Route::prefix('envato/licenses')->group(function () {
        Route::post('/', [EnvatoLicenseController::class, 'create'])->name('envato.licenses.create');
        Route::post('activate', [EnvatoLicenseController::class, 'activate'])->name('envato.licenses.activate');
        Route::post('deactivate', [EnvatoLicenseController::class, 'deactivate'])->name('envato.licenses.deactivate');
        Route::post('validate', [EnvatoLicenseController::class, 'validate'])->name('envato.licenses.validate');
        Route::get('status/{purchase_code}', [EnvatoLicenseController::class, 'status'])->name('envato.licenses.status');
        Route::post('renew', [EnvatoLicenseController::class, 'renew'])->name('envato.licenses.renew');
    });

    // Bulk Operations
    Route::prefix('bulk/licenses')->group(function () {
        Route::post('create', [BulkLicenseController::class, 'create'])->name('bulk.licenses.create');
        Route::post('validate', [BulkLicenseController::class, 'validate'])->name('bulk.licenses.validate');
        Route::post('renew', [BulkLicenseController::class, 'renew'])->name('bulk.licenses.renew');
    });
});