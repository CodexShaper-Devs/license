<?php

namespace App\Services\Envato;

use App\Exceptions\EnvatoVerificationException;
use App\Models\License;
use App\Models\EnvatoLicense;
use App\Models\EnvatoPurchase;
use App\Models\LicenseDomain;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Hash;

class EnvatoLicenseService
{
    private const CURRENT_TIME = '2025-02-10 12:25:26';
    private const CURRENT_USER = 'maab16';
    private const CACHE_TTL = 3600; // 1 hour
    private string $envatoToken = '';
    private string $itemId = '';

    public function __construct(
        private readonly LicenseService $licenseService
    ) {
        $this->envatoToken = config('licensing.envato.personal_token');
    }

    private function mapLicenseFeatures(string $envatoLicenseType, string $itemId): array
    {
        $productConfig = config("envato.products.{$itemId}");
        
        if (!$productConfig) {
            throw new EnvatoVerificationException("Invalid product ID: {$itemId}");
        }

        $envatoLicenseType = strtolower(str_replace(' ', '_', $envatoLicenseType));
        $licenseType = 'regular';

        if ($envatoLicenseType !== 'regular_license') {
            $licenseType = 'extended';
        }

        $licenseConfig = $productConfig['licenses'][$licenseType] ?? null;

        if (!$licenseConfig) {
            throw new EnvatoVerificationException(
                "Unknown license type: {$envatoLicenseType}"
            );
        }

        return [
            'seats' => $licenseConfig['seats'],
            'license_type' => $licenseType,
            'features' => [
                'updates' => $licenseConfig['features']['updates'],
                'support' => $licenseConfig['features']['support'],
                'domains' => $licenseConfig['domains']['production'],
                'local_domains' => $licenseConfig['domains']['local'],
                'multisite' => $licenseConfig['features']['multisite'],
                'white_label' => $licenseConfig['features']['white_label']
            ],
            'support_period' => $licenseConfig['support_period'],
            'product' => [
                'id' => $productConfig['id'],
                'plan_id' => $productConfig['licenses'][$licenseType]['plan_id'],
                'name' => $productConfig['name'],
                'metadata' => $productConfig['metadata']
            ]
        ];
    }

    public function convertToLicense(string $purchaseCode, $itemId, $email): License
    {
        Log::info('Starting license conversion', [
            'purchase_code' => $purchaseCode,
            'timestamp' => config('envato.system.date'),
            'user' => config('envato.system.user')
        ]);

        // Verify purchase code
        $purchaseData = $this->verifyPurchaseCode($purchaseCode, $itemId);

        $itemId = $purchaseData['item']['id'];

        // Map features
        $features = $this->mapLicenseFeatures($purchaseData['license'], $itemId);

        // Calculate support expiry
        $supportExpiry = Carbon::parse($purchaseData['sold_at'])
            ->addMonths($features['support_period']);

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $purchaseData['buyer'],
                'email' => $email,
                'password' => Hash::make($purchaseCode),
                'is_admin' => 0,
                'email_verified_at' => Carbon::now()->toDateTimeString(),
            ]);
        }

        $license = $this->licenseService->createLicense([
            'product_id' => $features['product']['id'],
            'plan_id' => $features['product']['plan_id'],
            'user_id' =>  $user->id,
            'type' => 'lifetime',
            'source' => 'envato',
            'source_purchase_code' => $purchaseCode,
            'status' => 'active',
            'purchased_seats' => $features['seats'] ?? 1,
            'features' => $features['features'],
            'valid_from' => $purchaseData['sold_at'],
            'valid_until' => $supportExpiry,
            'status' => 'active'
        ]);

        return $license;
    }

    public function verifyPurchaseCode(string $code, $itemId): array
    {
        $this->itemId = $itemId;
        // $cacheKey = "envato_purchase_{$itemId}_{$code}";

        // return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($code) {
            Log::info('Verifying Envato purchase code', [
                'purchase_code' => $code,
                'timestamp' => self::CURRENT_TIME,
                'user' => self::CURRENT_USER
            ]);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->envatoToken}"
            ])->get("https://api.envato.com/v3/market/author/sale", [
                'code' => $code
            ]);

            if (!$response->successful()) {
                Log::error('Envato API verification failed', [
                    'purchase_code' => $code,
                    'response' => $response->body(),
                    'timestamp' => self::CURRENT_TIME,
                    'user' => self::CURRENT_USER
                ]);

                throw new EnvatoVerificationException(
                    "Failed to verify purchase code: {$response->body()}"
                );
            }

            $data = $response->json();

            // Validate item ID
            if ((string) $data['item']['id'] !== $this->itemId) {
                throw new EnvatoVerificationException(
                    "Invalid item ID. Expected {$this->itemId}, got {$data['item']['id']}"
                );
            }

            return $data;
        // });
    }

    public function deactivateWithPurchaseCode($data): array {
        try {
            $domain = LicenseDomain::where('domain', $data['domain'])
                ->where('is_active', true)
                ->firstOrFail();

            if (! $domain) {
                throw new EnvatoVerificationException('Domain not found');
            }

            $license = $domain->license ?? null;

            if (! $license) {
                throw new EnvatoVerificationException('License not found');
            }

            if ($license->source_purchase_code !== $data['purchase_code']) {
                throw new EnvatoVerificationException('Invalid purchase code');
            }

            $activation = $domain->licenseActivation ?? null;

            if (! $activation) {
                throw new EnvatoVerificationException('Activation not found');
            }

            return $this->licenseService->deactivateByDomain(
                $license->key, 
                $data['domain'], 
                $activation->activation_token
            );

        } catch (\Exception $e) {
            Log::error('Domain-based deactivation failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

}