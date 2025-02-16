<?php

namespace Database\Seeders;

use App\Models\LicensePlan;
use Illuminate\Database\Seeder;

class LicensePlanSeeder extends Seeder
{
    private const USER = 'maab16';

    public function run(): void
    {
        $plans = [
            [
                'id' => '9e39b4f5-5b99-4976-b40f-a6e40ba1d380',
                'name' => 'Regular License',
                'slug' => 'regular-license',
                'description' => 'Single domain license for one year',
                'features' => [
                    'single_domain' => true,
                    'updates' => '1 year',
                    'support' => '6 months',
                    'installments' => false
                ],
                'restrictions' => [
                    'max_seats' => 1,
                    'allow_subdomains' => false,
                    'allow_local_domains' => true
                ],
                'settings' => [
                    'renewal_reminder_days' => 30,
                    'grace_period_days' => 7,
                    'hardware_verification' => true
                ],
                'price_per_seat' => 59.00,
                'renewal_per_seat' => 29.00,
                'type' => 'subscription',
                'duration_months' => 12,
                'status' => 'active',
                'created_by' => self::USER,
            ],
            [
                'id' => '9e39b4f5-5d24-491b-a0db-8613a2bcf73a',
                'name' => 'Extended License',
                'slug' => 'extended-license',
                'description' => 'Multiple domain license with lifetime updates',
                'features' => [
                    'multiple_domains' => true,
                    'updates' => 'lifetime',
                    'support' => '12 months',
                    'installments' => true
                ],
                'restrictions' => [
                    'max_seats' => 5,
                    'allow_subdomains' => true,
                    'allow_local_domains' => true
                ],
                'settings' => [
                    'renewal_reminder_days' => null,
                    'grace_period_days' => 14,
                    'hardware_verification' => true
                ],
                'price_per_seat' => 299.00,
                'renewal_per_seat' => 0.00,
                'type' => 'lifetime',
                'duration_months' => 0,
                'status' => 'active',
                'created_by' => self::USER,
            ],
            [
                'id' => '9e39b4f5-5e33-4626-95b3-1f34b1412d16',
                'name' => 'Trial License',
                'slug' => 'trial-license',
                'description' => 'Try all features for 14 days',
                'features' => [
                    'single_domain' => true,
                    'updates' => '14 days',
                    'support' => '14 days',
                    'installments' => false
                ],
                'restrictions' => [
                    'max_seats' => 1,
                    'allow_subdomains' => false,
                    'allow_local_domains' => true
                ],
                'settings' => [
                    'renewal_reminder_days' => 3,
                    'grace_period_days' => 0,
                    'hardware_verification' => true
                ],
                'price_per_seat' => 0.00,
                'renewal_per_seat' => 0.00,
                'type' => 'trial',
                'duration_months' => 0,
                'trial_days' => 14,
                'status' => 'active',
                'created_by' => self::USER,
            ]
        ];

        foreach ($plans as $plan) {
            LicensePlan::create($plan);
        }
    }
}