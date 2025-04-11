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
            'id' => '9e39b4f5-5995-4679-9bdf-ea26a6bd52ce', // Internal Product ID
            'name' => 'Edulab LMS - Laravel Learning Management System with Tailwind CSS',
            'licenses' => [
                'regular' => [
                    'plan_id' => '9e39b4f5-5b99-4976-b40f-a6e40ba1d380', // Internal Plan ID
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
                    'plan_id' => '9e39b4f5-5d24-491b-a0db-8613a2bcf73a', // Internal Plan ID
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
        '51980727' => [ // Envato Item ID
            'id' => '9e39b4f5-5995-4679-9bdf-ea26a6bd52ce', // Internal Product ID
            'name' => 'Holaa - OTT Platform and Video Streaming WordPress Theme',
            'licenses' => [
                'regular' => [
                    'plan_id' => '9e39b4f5-5b99-4976-b40f-a6e40ba1d380', // Internal Plan ID
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
                    'plan_id' => '9e39b4f5-5d24-491b-a0db-8613a2bcf73a', // Internal Plan ID
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
                'category' => 'Dioexpress',
                'platform' => 'WordPress',
                'version' => '1.0.0'
            ]
        ],
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