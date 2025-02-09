<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\EncryptionService;

class LicenseApiAuthentication
{
    public function __construct(private readonly EncryptionService $encryption)
    {}

    public function handle(Request $request, Closure $next)
    {
        if (!$this->validateApiRequest($request)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API signature'
            ], 401);
        }

        return $next($request);
    }

    private function validateApiRequest(Request $request): bool
    {
        $signature = $request->header('X-License-Signature');
        $timestamp = $request->header('X-License-Timestamp');
        $apiKey = $request->header('X-License-API-Key');

        if (!$signature || !$timestamp || !$apiKey) {
            return false;
        }

        // Prevent replay attacks - timestamp should be within 5 minutes
        if (abs(time() - strtotime($timestamp)) > 300) {
            return false;
        }

        $payload = $request->getContent() . $timestamp . $apiKey;
        return $this->encryption->verify($payload, $signature, $apiKey);
    }
}