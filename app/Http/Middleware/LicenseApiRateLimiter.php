<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LicenseApiRateLimiter
{
    public function __construct(private readonly RateLimiter $limiter)
    {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'license-api:' . $request->ip();

        if ($this->limiter->tooManyAttempts($key, 60)) {
            return response()->json([
                'error' => 'Too Many Requests',
                'message' => 'API rate limit exceeded'
            ], 429);
        }

        $this->limiter->hit($key);
        return $next($request);
    }
}