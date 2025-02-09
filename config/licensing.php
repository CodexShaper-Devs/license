<?php

return [
    'encryption' => [
        'cipher' => 'AES-256-CBC',
        'key_length' => 32,
        'hash_algo' => 'sha256',
        'pbkdf2_iterations' => 10000,
    ],
    'license' => [
        'key_entropy' => 32,
        'signature_algorithm' => 'sha512',
        'validation_frequency' => 86400, // 24 hours
        'grace_period' => 72, // hours
        'hardware_binding' => true,
    ],
    'envato' => [
        'api_key' => env('ENVATO_API_KEY'),
        'personal_token' => env('ENVATO_PERSONAL_TOKEN'),
        'sandbox' => env('ENVATO_SANDBOX', false),
    ],
];