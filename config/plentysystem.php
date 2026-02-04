<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PlentySystem API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your PlentySystem API credentials and settings here.
    | The base URL should be in the format: https://p{myPID}.my.plentysystems.com
    |
    */

    'base_url' => env('PLENTYSYSTEM_BASE_URL'),

    'username' => env('PLENTYSYSTEM_USERNAME'),

    'password' => env('PLENTYSYSTEM_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | API Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout in seconds for API requests.
    |
    */

    'timeout' => env('PLENTYSYSTEM_TIMEOUT', 30),
];
