<?php

namespace Tests\Feature\Licenses;

use App\Events\LicenseActivated;
use App\Events\LicenseDeactivated;
use App\Exceptions\LicenseActivationException;
use Illuminate\Support\Facades\Event;

class LicenseActivationTest extends BaseLicenseTest
{
    private array $deviceData;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->deviceData = [
            'device_identifier' => 'TEST-DEVICE-' . time(),
            'device_name' => 'Test MacBook Pro',
            'hardware' => [
                'cpu_id' => 'CPU-' . uniqid(),
                'disk_id' => 'DISK-' . uniqid(),
                'mac_address' => '00:11:22:33:44:55'
            ],
            'domain' => 'test.example.com',
            'metadata' => [
                'os' => 'macOS 12.0',
                'app_version' => '1.0.0'
            ]
        ];
    }

    /** @test */
    public function it_can_activate_license()
    {
        Event::fake();
        
        $license = $this->createTestLicense();
        
        $result = $this->licenseService->activateLicense(
            $license->key,
            $this->deviceData
        );

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['activation_id']);
        
        $this->assertDatabaseHas('license_activations', [
            'license_id' => $license->id,
            'device_identifier' => $this->deviceData['device_identifier'],
            'is_active' => true
        ]);

        Event::assertDispatched(LicenseActivated::class);
    }

    /** @test */
    public function it_prevents_duplicate_device_activation()
    {
        $license = $this->createTestLicense();
        
        // First activation
        $this->licenseService->activateLicense($license->key, $this->deviceData);

        // Attempt second activation
        $this->expectException(LicenseActivationException::class);
        $this->licenseService->activateLicense($license->key, $this->deviceData);
    }

    /** @test */
    public function it_can_deactivate_license()
    {
        Event::fake();
        
        $license = $this->createTestLicense();
        
        // First activate
        $activation = $this->licenseService->activateLicense(
            $license->key,
            $this->deviceData
        );

        // Then deactivate
        $result = $this->licenseService->deactivateLicense(
            $license->key,
            $this->deviceData['device_identifier']
        );

        $this->assertTrue($result['success']);
        
        $this->assertDatabaseHas('license_activations', [
            'license_id' => $license->id,
            'device_identifier' => $this->deviceData['device_identifier'],
            'is_active' => false
        ]);

        Event::assertDispatched(LicenseDeactivated::class);
    }
}