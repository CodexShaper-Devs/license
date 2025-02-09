<?php

namespace App\Services\Marketplace\Verifiers;

use App\Services\Marketplace\MarketplaceVerifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class OtherMarketplaceVerifier implements MarketplaceVerifier
{
    public function getSourceIdentifier(): string
    {
        return 'other';
    }

    public function verifyPurchase(string $purchaseCode): array
    {
        $cacheKey = 'other_purchase_' . $purchaseCode;
        if ($cachedData = Cache::get($cacheKey)) {
            return $cachedData;
        }

        // Implement verification logic for other marketplaces
        // This is just an example structure
        $response = Http::withHeaders([
            'Authorization' => config('services.other_marketplace.token')
        ])->get(config('services.other_marketplace.api_url') . '/verify/' . $purchaseCode);

        if (!$response->successful()) {
            throw new \Exception('Failed to verify purchase code');
        }

        $data = $response->json();
        
        $verificationData = [
            'source' => 'other',
            'purchase_code' => $purchaseCode,
            'buyer' => $data['customer_name'] ?? null,
            'sold_at' => $data['purchase_date'] ?? null,
            'license_type' => $data['license_type'] ?? 'standard',
            'verified_at' => now()->toIso8601String()
        ];

        // Cache the verification for 24 hours
        Cache::put($cacheKey, $verificationData, now()->addHours(24));

        return $verificationData;
    }
}