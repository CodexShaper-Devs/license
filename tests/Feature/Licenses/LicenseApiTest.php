<?php

namespace Tests\Feature\Api;

use Tests\Feature\Licenses\BaseLicenseTest;
use Illuminate\Testing\Fluent\AssertableJson;

class LicenseApiTest extends BaseLicenseTest
{
    /** @test */
    public function it_can_activate_license_via_api()
    {
        $license = $this->createTestLicense();

        $response = $this->postJson('/api/v1/licenses/activate', [
            'license_key' => $license->key,
            'device_identifier' => 'TEST-DEVICE-' . time(),
            'device_name' => 'Test Device',
            'hardware' => [
                'cpu_id' => 'CPU-' . uniqid(),
                'disk_id' => 'DISK-' . uniqid(),
            ],
            'domain' => 'test.example.com'
        ]);

        $response->assertStatus(200)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('success')
                     ->has('activation_id')
                     ->has('features')
                     ->has('expires_at')
                     ->etc()
            );
    }

    /** @test */
    public function it_can_validate_license_via_api()
    {
        $license = $this->createTestLicense();

        $response = $this->postJson('/api/v1/licenses/validate', [
            'license_key' => $license->key,
            'domain' => 'example.com',
            'ip' => '127.0.0.1'
        ]);

        $response->assertStatus(200)
            ->assertJson(['valid' => true]);
    }

    /** @test */
    public function it_returns_error_for_invalid_license()
    {
        $response = $this->postJson('/api/v1/licenses/validate', [
            'license_key' => 'INVALID-KEY',
            'domain' => 'example.com'
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'License not found']);
    }
}