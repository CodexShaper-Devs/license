<?php

namespace App\Repositories;

use App\Models\License;
use App\Services\EncryptionService;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class LicenseRepository
{
    private const CACHE_TTL = 3600;
    private const CACHE_PREFIX = 'license:';
    private const TIMESTAMP = '2025-02-09 07:40:27';
    private const USER = 'maab16';

    public function create(array $data): License
    {
        // Remove id if it's being passed
        unset($data['id']);
        
        $license = License::create($data);
        $this->cacheKey($license->key, $license);
        return $license;
    }

    public function findByKey(string $key): ?License
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key) {
            return License::where('key', $key)->first();
        });
    }

    public function update(License $license, array $data): License
    {
        $license->update(array_merge($data, [
            'updated_by' => self::USER,
            'updated_at' => self::TIMESTAMP
        ]));

        $this->cacheKey($license->key, $license->fresh());
        return $license;
    }

    public function logLicenseEvent(License $license, string $event, array $data): void
    {
        $license->logs()->create([
            'event' => $event,
            'data' => array_merge($data, [
                'timestamp' => self::TIMESTAMP,
                'user' => self::USER
            ]),
            'created_by' => self::USER
        ]);
    }

    private function cacheKey(string $key, License $license): void
    {
        Cache::put(
            self::CACHE_PREFIX . $key, 
            $license, 
            Carbon::parse(self::TIMESTAMP)->addSeconds(self::CACHE_TTL)
        );
    }

    public function invalidateCache(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX . $key);
    }

    public function validateSignature(License $license): bool
    {
        if (!isset($license->metadata['_security']['encrypted_data'])) {
            return false;
        }

        return app(EncryptionService::class)->verify(
            $license->metadata['_security']['encrypted_data'],
            $license->signature,
            $license->auth_key_id
        );
    }

    /**
     * Find a license by Envato purchase code
     *
     * @param string $purchaseCode
     * @return License|null
     */
    public function findByPurchaseCode(string $purchaseCode): ?License
    {
        $cacheKey = self::CACHE_PREFIX . 'envato:' . $purchaseCode;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($purchaseCode) {
            return License::where('source', 'envato')
                         ->where('source_purchase_code', $purchaseCode)
                         ->first();
        });
    }

    /**
     * Invalidate purchase code cache
     *
     * @param string $purchaseCode
     * @return void
     */
    public function invalidatePurchaseCodeCache(string $purchaseCode): void
    {
        Cache::forget(self::CACHE_PREFIX . 'envato:' . $purchaseCode);
    }
}