<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Paynecta API Credentials
    |--------------------------------------------------------------------------
    |
    | Your Paynecta API key and registered email address.
    | Get these from your dashboard at https://paynecta.co.ke/api
    |
    | SECURITY: Never commit these values to version control!
    | Always use environment variables in your .env file.
    |
    */
    'api_key' => env('PAYNECTA_API_KEY'),
    
    'email' => env('PAYNECTA_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for Paynecta API endpoints.
    | You typically don't need to change this unless using a test environment.
    |
    */
    'base_url' => env('PAYNECTA_BASE_URL', 'https://paynecta.co.ke/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time (in seconds) to wait for API responses.
    | Increase this if you're experiencing timeout issues.
    |
    */
    'timeout' => env('PAYNECTA_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Enable Logging
    |--------------------------------------------------------------------------
    |
    | Log all API requests and responses for debugging purposes.
    | WARNING: Disable in production to avoid logging sensitive data.
    |
    */
    'logging' => env('PAYNECTA_LOGGING', false),

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | Specify which log channel to use when logging is enabled.
    | Uses Laravel's default log channel if not specified.
    |
    */
    'log_channel' => env('PAYNECTA_LOG_CHANNEL', 'stack'),
];