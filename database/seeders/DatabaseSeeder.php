<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create test user
        User::create([
            'name' => 'maab16',
            'email' => 'maab16@example.com',
            'password' => bcrypt('password'),
            'created_at' => '2025-02-06 10:52:45',
            'updated_at' => '2025-02-06 10:52:45'
        ]);

        // Create test product
        Product::create([
            'id' => Str::uuid(),
            'name' => 'Premium Invoice Manager',
            'slug' => 'premium-invoice-manager',
            'description' => 'Professional invoice management software for businesses',
            'version' => '2.0.0',
            'type' => 'software',
            'price' => 299.99,
            'is_active' => true,
            'metadata' => [
                'author' => 'maab16',
                'website' => 'https://invoicemanager.com',
                'support_email' => 'support@invoicemanager.com',
                'minimum_php_version' => '8.1',
                'release_date' => '2025-02-06'
            ],
            'settings' => [
                'max_seats' => 10,
                'trial_days' => 14,
                'requires_domain_validation' => true,
                'check_in_interval' => 7
            ],
            'created_at' => '2025-02-06 10:52:45',
            'updated_at' => '2025-02-06 10:52:45'
        ]);
    }
}