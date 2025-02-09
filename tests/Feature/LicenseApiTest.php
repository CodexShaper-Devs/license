<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LicenseApiTest extends TestCase
{
    use RefreshDatabase;

    private const TIMESTAMP = '2025-02-09 05:33:29';
    private const USER = 'maab16';

    /**
     * Test creating a custom license
     */
    public function test_create_custom_license()
    {
        $response = $this->postJson('/api/licenses', [
            'source' => 'custom',
            'type' => 'subscription',
            'seats' => 5,
            'features' => [
                [
                    'name' => 'api_access',
                    'enabled' => true,
                    'limit' => 1000
                ],
                [
                    'name' => 'premium_support',
                    'enabled' => true
                ]
            ],
            'restrictions' => [
                'domain' => 'example.com',
                'environment' => 'production'
            ],
            'valid_from' => self::TIMESTAMP,
            'valid_until' => '2026-02-09 05:33:29',
            'metadata' => [
                'client_name' => 'Acme Corp',
                'project_name' => 'E-commerce Platform',
                'notes' => 'Enterprise client'
            ],
            'settings' => [
                'check_in_interval' => 7,
                'hardware_validation' => true,
                'domain_validation' => true
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'key',
                    'type',
                    'status',
                    'seats',
                    'features',
                    'valid_until'
                ]
            ]);
    }

    /**
     * Test creating an Envato license
     */
    public function test_create_envato_license()
    {
        $response = $this->postJson('/api/licenses', [
            'source' => 'envato',
            'source_purchase_code' => '1234-5678-90AB-CDEF',
            'type' => 'onetime',
            'seats' => 1,
            'features' => [
                [
                    'name' => 'standard_support',
                    'enabled' => true
                ]
            ],
            'valid_from' => self::TIMESTAMP,
            'metadata' => [
                'client_name' => 'John Doe',
                'project_name' => 'Personal Blog'
            ],
            'settings' => [
                'check_in_interval' => 30,
                'hardware_validation' => true
            ]
        ]);

        $response->assertStatus(201);
    }

    /**
     * Test activating a license
     */
    public function test_activate_license()
    {
        // First create a license
        $license = $this->create_test_license();

        $response = $this->postJson('/api/licenses/activate', [
            'license_key' => $license->key ?? '',
            'device_identifier' => '4C4C4544-0043-4B10-8080-B4C04F564433',
            'device_name' => 'Development Laptop',
            'domain' => 'dev.example.com',
            'hardware' => [
                'cpu_id' => 'BFEBFBFF000906A3',
                'disk_id' => 'S3YUNX0N516097K',
                'mac_address' => '00:1B:44:11:3A:B7',
                'os' => 'Linux 5.15.0-1033-azure'
            ],
            'metadata' => [
                'environment' => 'development',
                'framework_version' => 'Laravel 10.0',
                'php_version' => '8.2.0'
            ],
            'ip_address' => '192.168.1.100'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'activation_id',
                'features',
                'expires_at',
                'check_in_required_at'
            ]);
    }

    /**
     * Test deactivating a license
     */
    public function test_deactivate_license()
    {
        // First create and activate a license
        $license = $this->create_and_activate_test_license();

        $response = $this->postJson('/api/licenses/deactivate', [
            'license_key' => $license->key ?? '',
            'device_identifier' => '4C4C4544-0043-4B10-8080-B4C04F564433',
            'reason' => 'Moving to new server',
            'metadata' => [
                'environment' => 'production',
                'deactivation_type' => 'planned'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'deactivated_at',
                'deactivated_by'
            ]);
    }

    /**
     * Test checking license status
     */
    public function test_check_license_status()
    {
        // First create and activate a license
        $license = $this->create_and_activate_test_license();
        $license_key = $license->key ?? '';

        $response = $this->getJson("/api/licenses/{$license_key}/status", [
            'device_identifier' => '4C4C4544-0043-4B10-8080-B4C04F564433'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'license_key',
                'status',
                'type',
                'valid_until',
                'seats',
                'features',
                'activation'
            ]);
    }

    private function create_test_license()
    {
        // Implementation of test license creation
    }

    private function create_and_activate_test_license()
    {
        // Implementation of test license creation and activation
    }
}