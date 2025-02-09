<?php

namespace App\Services\Marketplace\Verifiers;

use App\Services\Marketplace\MarketplaceVerifier;

class CustomVerifier implements MarketplaceVerifier
{
    public function getSourceIdentifier(): string
    {
        return 'custom';
    }

    public function verifyPurchase(string $purchaseCode): array
    {
        // Your custom marketplace verification logic
        return [
            'source' => 'custom',
            'purchase_code' => $purchaseCode,
            // Add other relevant data
        ];
    }
}