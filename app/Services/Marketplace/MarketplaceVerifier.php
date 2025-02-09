<?php

namespace App\Services\Marketplace;

interface MarketplaceVerifier
{
    public function verifyPurchase(string $purchaseCode): array;
    public function getSourceIdentifier(): string;
}