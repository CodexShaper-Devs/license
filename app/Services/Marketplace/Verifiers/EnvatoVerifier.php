<?php

namespace App\Services\Marketplace\Verifiers;

use App\Services\Marketplace\MarketplaceVerifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class EnvatoVerifier implements MarketplaceVerifier
{
    public function getSourceIdentifier(): string
    {
        return 'envato';
    }

    public function verifyPurchase(string $purchaseCode): array
    {
        $cacheKey = 'envato_purchase_' . $purchaseCode;
        if ($cachedData = Cache::get($cacheKey)) {
            return $cachedData;
        }

        // Envato verification logic...
        $response = Http::withToken(config('services.envato.token'))
            ->get("https://api.envato.com/v3/market/author/sale/{$purchaseCode}");

        $data = $response->json();
        
        return [
            'source' => 'envato',
            'purchase_code' => $purchaseCode,
            'buyer' => $data['buyer'],
            'sold_at' => $data['sold_at'],
            'license_type' => $data['license'],
            'supported_until' => $data['supported_until'],
            'item' => [
                'id' => $data['item']['id'],
                'name' => $data['item']['name']
            ]
        ];
    }
}