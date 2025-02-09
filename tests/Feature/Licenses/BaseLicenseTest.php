<?php

namespace Tests\Feature\Licenses;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\License;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BaseLicenseTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;
    protected LicenseService $licenseService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'type' => 'software',
            'price' => 99.99,
            'is_active' => true
        ]);

        $this->licenseService = app(LicenseService::class);
    }

    protected function createTestLicense(array $overrides = []): License
    {
        return $this->licenseService->createLicense(array_merge([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'type' => 'subscription',
            'seats' => 5,
            'valid_from' => now(),
            'valid_until' => now()->addYear(),
            'features' => ['feature1', 'feature2'],
            'restrictions' => [
                'domain' => 'example.com'
            ]
        ], $overrides));
    }
}