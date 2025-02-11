<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Envato API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Envato API integration including tokens and API endpoints
    |
    */
    'api' => [
        'api_key' => env('ENVATO_API_KEY'),
        'personal_token' => env('ENVATO_PERSONAL_TOKEN'),
        'sandbox' => env('ENVATO_SANDBOX', false),
        'base_url' => 'https://api.envato.com/v3',
        'timeout' => 30,
        'retry' => [
            'times' => 3,
            'sleep' => 1
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Mapping Configuration
    |--------------------------------------------------------------------------
    |
    | Maps Envato item IDs to your internal product configurations
    |
    */
    'products' => [
        '55973900' => [ // Envato Item ID
            'id' => '9e2d8aee-1637-4827-8948-d362efebaa50',
            'name' => 'Edulab LMS - Laravel Learning Management System with Tailwind CSS',
            'licenses' => [
                'regular' => [
                    'plan_id' => '9e2d8aee-2116-4b21-b5e9-27c7ff33267c',
                    'seats' => 1,
                    'domains' => [
                        'production' => 1,
                        'local' => -1 // Unlimited
                    ],
                    'features' => [
                        'updates' => true,
                        'support' => true,
                        'multisite' => false,
                        'white_label' => false
                    ],
                    'support_period' => 6 // months
                ],
                'extended' => [
                    'plan_id' => '9e2d8aee-22b1-4eee-a3e6-05afcbbd5c26',
                    'seats' => 1,
                    'domains' => [
                        'production' => 5,
                        'local' => -1 // Unlimited
                    ],
                    'features' => [
                        'updates' => true,
                        'support' => true,
                        'multisite' => true,
                        'white_label' => true
                    ],
                    'support_period' => 12 // months
                ]
            ],
            'metadata' => [
                'category' => 'LMS',
                'platform' => 'Laravel',
                'version' => '1.0'
            ]
        ],
        '87654321' => [ // Another product
            'id' => 'LMS-STARTER',
            'name' => 'LMS Hub Starter',
            'licenses' => [
                'regular' => [
                    'seats' => 1,
                    'domains' => [
                        'production' => 1,
                        'local' => -1
                    ],
                    'features' => [
                        'updates' => true,
                        'support' => true,
                        'multisite' => false,
                        'white_label' => false
                    ],
                    'support_period' => 6
                ]
            ],
            'metadata' => [
                'category' => 'LMS',
                'platform' => 'Laravel',
                'version' => '1.0'
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for domain validation and restrictions
    |
    */
    'domains' => [
        'local_patterns' => [
            '/\.test$/',
            '/\.local$/',
            '/\.localhost$/',
            '/^localhost/',
            '/^127\.0\.0\.1$/',
            '/^::1$/',
            '/\.dev$/'
        ],
        'restricted' => [
            'patterns' => [
                '/\.example\.com$/',
                '/\.envato\.com$/'
            ],
            'domains' => [
                'example.com',
                'envato.com'
            ]
        ],
        'verification' => [
            'methods' => ['dns', 'file'],
            'default' => 'dns',
            'ttl' => 3600 // 1 hour
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Support Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for support period and verification
    |
    */
    'support' => [
        'verify_period' => 24, // hours
        'grace_period' => 15, // days
        'expiry_notification' => [
            'days_before' => [30, 15, 7, 3, 1],
            'channels' => ['mail', 'database']
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for caching Envato API responses
    |
    */
    'cache' => [
        'enabled' => true,
        'ttl' => [
            'verification' => 3600, // 1 hour
            'purchase' => 86400, // 24 hours
            'license' => 3600 // 1 hour
        ],
        'prefix' => 'envato_'
    ],

    /*
    |--------------------------------------------------------------------------
    | System Configuration
    |--------------------------------------------------------------------------
    |
    | System-wide configuration for dates and users
    |
    */
    'system' => [
        'date' => '2025-02-11 06:53:23',
        'user' => 'maab16',
        'timezone' => 'UTC'
    ]
];