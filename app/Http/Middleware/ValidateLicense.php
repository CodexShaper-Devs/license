<?php

namespace App\Http\Middleware;

use Closure;
use App\Facades\LicenseManager;
use Illuminate\Http\Request;

class ValidateLicense
{
    public function handle(Request $request, Closure $next)
    {
        $license = $request->header('X-License-Key');
        
        if (!$license) {
            return response()->json(['error' => 'License key required'], 403);
        }

        $hardwareInfo = [
            'cpu_id' => $request->header('X-Hardware-CPU'),
            'disk_id' => $request->header('X-Hardware-Disk'),
            'mac_address' => $request->header('X-Hardware-MAC'),
        ];

        if (!LicenseManager::validateLicense($license, $hardwareInfo)) {
            return response()->json(['error' => 'Invalid license'], 403);
        }

        return $next($request);
    }
}