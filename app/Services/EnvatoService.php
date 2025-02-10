<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;
use Carbon\Carbon;

class EnvatoService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_PREFIX = 'envato:purchase:';
    private const TIMESTAMP = '2025-02-09 07:28:26';
    private const USER = 'maab16';

    public function __construct(
        private string $apiToken,
        private Http $http
    ) {
        $this->apiToken = $apiToken;
    }

    public function verifyPurchase(string $purchaseCode): array
    {
        return Cache::remember(
            self::CACHE_PREFIX . $purchaseCode,
            self::CACHE_TTL,
            function () use ($purchaseCode) {
                return $this->makeVerificationRequest($purchaseCode);
            }
        );
    }

    private function makeVerificationRequest(string $purchaseCode): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'User-Agent' => 'License Verification System'
            ])->get("https://api.envato.com/v3/market/author/sale/", [
                'code' => $purchaseCode
            ]);

            if (!$response->successful()) {
                throw new Exception(
                    'Failed to verify purchase code: ' . $response->json('message', 'Unknown error')
                );
            }

            $data = $response->json();
            return $this->formatPurchaseData($data, $purchaseCode);

        } catch (\Exception $e) {
            throw new Exception(
                'Purchase code verification failed: ' . $e->getMessage()
            );
        }
    }

    private function formatPurchaseData(array $data, string $purchaseCode): array
    {
        return [
            'purchase_code' => $purchaseCode,
            'buyer' => $data['buyer'] ?? 'unknown',
            'buyer_email' => $data['buyer_email'] ?? null,
            'item_id' => $data['item']['id'] ?? null,
            'item_name' => $data['item']['name'] ?? null,
            'purchase_date' => $data['sold_at'] ?? null,
            'supported_until' => $data['supported_until'] ?? null,
            'license' => $data['license'] ?? 'regular',
            'verified_at' => self::TIMESTAMP,
            'verified_by' => self::USER
        ];
    }

    public function invalidateCache(string $purchaseCode): void
    {
        Cache::forget(self::CACHE_PREFIX . $purchaseCode);
    }

    public function validateSupport(string $purchaseCode): bool
    {
        $data = $this->verifyPurchase($purchaseCode);
        
        if (empty($data['supported_until'])) {
            return false;
        }

        return Carbon::parse($data['supported_until'])->isAfter(Carbon::parse(self::TIMESTAMP));
    }

    public function getItemDetails(string $itemId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'User-Agent' => 'License Verification System'
            ])->get("https://api.envato.com/v3/market/catalog/item", [
                'id' => $itemId
            ]);

            if (!$response->successful()) {
                throw new Exception(
                    'Failed to fetch item details: ' . $response->json('message', 'Unknown error')
                );
            }

            return array_merge($response->json(), [
                'fetched_at' => self::TIMESTAMP,
                'fetched_by' => self::USER
            ]);

        } catch (\Exception $e) {
            throw new Exception(
                'Failed to fetch item details: ' . $e->getMessage()
            );
        }
    }
}