<?php

use App\Http\Controllers\Api\LicenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/licenses')->group(function () {
    Route::post('create', [LicenseController::class, 'create']);
    Route::post('activate', [LicenseController::class, 'activate']);
    Route::post('validate', [LicenseController::class, 'validate']);
    Route::post('deactivate', [LicenseController::class, 'deactivate']);
});