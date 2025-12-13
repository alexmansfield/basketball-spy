<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for cross-origin resource sharing. The mobile app and any
    | web clients need to be explicitly allowed to make requests.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:8081,http://localhost:19006')),

    'allowed_origins_patterns' => [
        // Allow Expo dev clients
        '#^exp://.*$#',
        // Allow Laravel Cloud preview deployments
        '#^https://.*\.laravel\.cloud$#',
    ],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],

    'exposed_headers' => [],

    'max_age' => 86400, // 24 hours - browsers cache preflight response

    'supports_credentials' => true,

];
