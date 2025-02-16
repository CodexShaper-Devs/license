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
                'id' => '9e39b4f5-5657-4ba1-8abb-f905420b56a6',
                'name' => 'CodexShaper Framework Pro',
                'slug' => 'codexshaper-framework-pro',
                'description' => 'An MVC framework for WordPress developers',
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
                'id' => '9e39b4f5-5995-4679-9bdf-ea26a6bd52ce',
                'name' => 'Edulab LMS - Laravel Learning Management System with Tailwind CSS',
                'slug' => 'https://codecanyon.net/item/edulab-lms-laravel-learning-management-system-with-tailwind-css/55973900',
                'description' => 'Edulab LMS - Laravel Learning Management System with Tailwind CSS',
                'version' => '1.0.0',
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