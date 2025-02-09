<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LicenseIpCheck
{
    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $licenseKey = $request->header('X-License-Key');
        
        // Check for suspicious activity
        $cacheKey = "license_attempts:{$ip}";
        $attempts = Cache::get($cacheKey, 0);
        
        if ($attempts > 10) {
            return response()->json([
                'error' => 'Too many validation attempts'
            ], 429);
        }
        
        Cache::put($cacheKey, $attempts + 1, now()->addHour());

        // Validate domain against allowed domains
        $domain = $request->header('X-Domain');
        if (!$this->isValidDomain($licenseKey, $domain)) {
            return response()->json([
                'error' => 'Invalid domain for license'
            ], 403);
        }

        return $next($request);
    }

    private function isValidDomain($licenseKey, $domain)
    {
        // Implement domain validation logic
        return true; // Implement your validation logic
    }
}