<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Exceptions\EnvatoValidationException;
use App\Models\License;

class EnvatoLicenseService
{
    protected $apiKey;
    protected $personalToken;
    protected $isSandbox;

    public function __construct()
    {
        $this->apiKey = config('licensing.envato.api_key');
        $this->personalToken = config('licensing.envato.personal_token');
        $this->isSandbox = config('licensing.envato.sandbox');
    }

    public function validatePurchaseCode(string $purchaseCode): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->personalToken,
            'User-Agent' => 'License Manager',
        ])->get("https://api.envato.com/v3/market/author/sale?code={$purchaseCode}");

        if (!$response->successful()) {
            throw new EnvatoValidationException('Failed to validate Envato purchase code');
        }

        $data = $response->json();
        
        return [
            'valid' => true,
            'buyer' => $data['buyer'],
            'purchase_date' => $data['sold_at'],
            'supported_until' => $data['supported_until'],
            'license' => $data['license'],
        ];
    }

    public function registerPurchase(string $purchaseCode): void
    {
        $validation = $this->validatePurchaseCode($purchaseCode);
        
        if ($validation['valid']) {
            // Create a local license record
            $licenseData = [
                'source' => 'envato',
                'purchase_code' => $purchaseCode,
                'buyer' => $validation['buyer'],
                'supported_until' => $validation['supported_until'],
                'license_type' => $validation['license'],
            ];

            // Generate a custom license key for local validation
            $licenseGenerator = app(LicenseGeneratorService::class);
            $licenseKey = $licenseGenerator->generateLicense($licenseData);

            // Store the license in your database
            License::create([
                'key' => $licenseKey,
                'source' => 'envato',
                'envato_purchase_code' => $purchaseCode,
                'data' => $licenseData,
            ]);
        }
    }
}