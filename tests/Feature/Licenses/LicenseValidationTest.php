<?php

namespace Tests\Feature\Licenses;

use App\Exceptions\LicenseValidationException;

class LicenseValidationTest extends BaseLicenseTest
{
    /** @test */
    public function it_validates_active_license()
    {
        $license = $this->createTestLicense();
        
        $result = $this->licenseService->validateLicense($license->key, [
            'domain' => 'example.com',
            'ip' => '127.0.0.1'
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEquals($license->features, $result['features']);
    }

    /** @test */
    public function it_fails_validation_for_expired_license()
    {
        $license = $this->createTestLicense([
            'valid_until' => now()->subDay()
        ]);

        $this->expectException(LicenseValidationException::class);
        
        $this->licenseService->validateLicense($license->key, [
            'domain' => 'example.com'
        ]);
    }

    /** @test */
    public function it_fails_validation_for_invalid_domain()
    {
        $license = $this->createTestLicense([
            'restrictions' => [
                'domain' => 'example.com'
            ]
        ]);

        $this->expectException(LicenseValidationException::class);
        
        $this->licenseService->validateLicense($license->key, [
            'domain' => 'invalid-domain.com'
        ]);
    }
}