<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    private const USER = 'maab16';

    public function run(): void
    {
        $products = [
            [
                'name' => 'Premium WordPress Theme',
                'slug' => 'premium-wordpress-theme',
                'description' => 'A professional WordPress theme for businesses',
                'version' => '1.0.0',
                'source' => 'envato',
                'source_product_id' => '12345678',
                'check_in_interval_days' => 7,
                'features' => [
                    'responsive_design' => true,
                    'woocommerce_support' => true,
                    'rtl_support' => true,
                    'custom_widgets' => true
                ],
                'settings' => [
                    'require_hardware_verification' => true,
                    'allow_local_domains' => true,
                    'max_failed_checks' => 3
                ],
                'status' => 'active',
                'created_by' => self::USER,
            ],
            [
                'name' => 'Laravel Admin Panel',
                'slug' => 'laravel-admin-panel',
                'description' => 'Complete admin panel solution for Laravel',
                'version' => '2.0.0',
                'check_in_interval_days' => 30,
                'features' => [
                    'user_management' => true,
                    'role_permissions' => true,
                    'api_support' => true,
                    'dashboard_widgets' => true
                ],
                'settings' => [
                    'require_hardware_verification' => true,
                    'allow_local_domains' => true,
                    'max_failed_checks' => 5
                ],
                'status' => 'active',
                'created_by' => self::USER,
            ]
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}