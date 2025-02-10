<?php

namespace App\Repositories;

use App\Models\License;
use App\Models\LicenseDomain;
use App\Services\EncryptionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class LicenseRepository
{
    private const CACHE_TTL = 3600;
    private const CACHE_PREFIX = 'license:';
    private const TIMESTAMP = '2025-02-09 15:43:09';
    private const USER = 'maab16';

    public function create(array $data): License
    {
        DB::beginTransaction();
        try {
            // Remove id if it's being passed
            unset($data['id']);
            
            $license = License::create($data);
            
            // Cache the license
            $this->cacheKey($license->key, $license);
            
            // Cache by purchase code if it exists
            if (isset($data['source_purchase_code'])) {
                $this->cachePurchaseCode($data['source_purchase_code'], $license);
            }

            DB::commit();
            return $license;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(License $license, array $data): License
    {
        DB::beginTransaction();
        try {
            $license->update(array_merge($data, [
                'updated_by' => self::USER,
                'updated_at' => self::TIMESTAMP
            ]));

            $license = $license->fresh();
            
            // Update cache
            $this->cacheKey($license->key, $license);
            if ($license->source_purchase_code) {
                $this->cachePurchaseCode($license->source_purchase_code, $license);
            }

            DB::commit();
            return $license;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function findByKey(string $licenseKey): ?License
    {
        return License::where('key', $licenseKey)
            ->with(['product', 'plan']) // Eager load relationships
            ->first();
    }

    public function findByPurchaseCode(string $purchaseCode): ?License
    {
        $cacheKey = self::CACHE_PREFIX . 'envato:' . $purchaseCode;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($purchaseCode) {
            return License::with(['domains', 'activations', 'product', 'plan'])
                         ->where('source', 'envato')
                         ->where('source_purchase_code', $purchaseCode)
                         ->first();
        });
    }

    public function findExpiring(int $daysThreshold = 30): Collection
    {
        $threshold = Carbon::parse(self::TIMESTAMP)->addDays($daysThreshold);
        
        return License::where('status', License::STATUS_ACTIVE)
                     ->where('type', License::TYPE_SUBSCRIPTION)
                     ->where('valid_until', '<=', $threshold)
                     ->where('valid_until', '>', Carbon::parse(self::TIMESTAMP))
                     ->where('renewal_reminder_sent_at', null)
                     ->get();
    }

    public function findOverdueCheckIns(int $gracePeriodDays = 3): Collection
    {
        $threshold = Carbon::parse(self::TIMESTAMP)->subDays($gracePeriodDays);
        
        return License::where('status', License::STATUS_ACTIVE)
                     ->where('next_check_in', '<', $threshold)
                     ->get();
    }

    public function validateSignature(License $license): bool
    {
        if (!$license->signature || !$license->auth_key_id) {
            return false;
        }

        try {
            return app(EncryptionService::class)->verify(
                new \ParagonIE\HiddenString\HiddenString($license->key),
                $license->signature,
                $license->auth_key_id
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    public function logLicenseEvent(License $license, string $event, array $data): void
    {
        $license->events()->create([
            'event_type' => $event,
            'event_data' => array_merge($data, [
                'timestamp' => self::TIMESTAMP,
                'user' => self::USER,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]),
            'created_by' => self::USER,
            'created_at' => self::TIMESTAMP
        ]);
    }

    public function activateDomain(License $license, array $domainData): LicenseDomain
    {
        DB::beginTransaction();
        try {
            $domain = $license->domains()->create(array_merge($domainData, [
                'created_by' => self::USER,
                'created_at' => self::TIMESTAMP,
                'is_active' => true
            ]));

            // Update license activated seats count
            $license->increment('activated_seats');
            
            // Refresh cache
            $this->cacheKey($license->key, $license->fresh());

            DB::commit();
            return $domain;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deactivateDomain(License $license, LicenseDomain $domain): void
    {
        DB::beginTransaction();
        try {
            $domain->update([
                'is_active' => false,
                'deactivated_at' => self::TIMESTAMP,
                'deactivated_by' => self::USER
            ]);

            // Update license activated seats count
            $license->decrement('activated_seats');
            
            // Refresh cache
            $this->cacheKey($license->key, $license->fresh());

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function cacheKey(string $key, License $license): void
    {
        Cache::put(
            self::CACHE_PREFIX . $key, 
            $license, 
            Carbon::parse(self::TIMESTAMP)->addSeconds(self::CACHE_TTL)
        );
    }

    private function cachePurchaseCode(string $purchaseCode, License $license): void
    {
        Cache::put(
            self::CACHE_PREFIX . 'envato:' . $purchaseCode,
            $license,
            Carbon::parse(self::TIMESTAMP)->addSeconds(self::CACHE_TTL)
        );
    }

    public function invalidateCache(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX . $key);
    }

    public function invalidatePurchaseCodeCache(string $purchaseCode): void
    {
        Cache::forget(self::CACHE_PREFIX . 'envato:' . $purchaseCode);
    }

    public function getActiveDomains(License $license): Collection
    {
        return $license->domains()
                      ->where('is_active', true)
                      ->get();
    }

    public function updateCheckInStatus(License $license, array $checkInData): void
    {
        DB::beginTransaction();
        try {
            $license->update([
                'last_check_in' => self::TIMESTAMP,
                'next_check_in' => Carbon::parse(self::TIMESTAMP)
                    ->addDays($license->product->check_in_interval_days),
                'failed_checks' => 0,
                'updated_by' => self::USER
            ]);

            $this->logLicenseEvent($license, 'check_in', $checkInData);
            
            // Refresh cache
            $this->cacheKey($license->key, $license->fresh());

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}