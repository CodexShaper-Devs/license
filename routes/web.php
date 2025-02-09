<?php

use App\Http\Controllers\LicenseController;
use App\Models\License;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/license/create', [LicenseController::class, 'index']);

