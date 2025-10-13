# Paynecta Laravel SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/paynecta/paynecta-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/paynecta/paynecta-laravel-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/paynecta/paynecta-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/paynecta/paynecta-laravel-sdk)
[![License](https://img.shields.io/packagist/l/paynecta/paynecta-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/paynecta/paynecta-laravel-sdk)

Official Laravel SDK for [Paynecta Payment API](https://paynecta.co.ke) - Simplify M-Pesa, Bank, and Wallet payment integrations in Kenya.

## Features

- 🚀 **Easy Integration** - Get started in minutes with simple configuration
- 💳 **M-Pesa Support** - Connect your M-Pesa Till or Paybill seamlessly
- 🏦 **Bank Accounts** - Link bank accounts for direct transfers
- 💰 **Digital Wallets** - Integrate with popular third-party wallets
- 🔔 **Real-time Webhooks** - Instant notifications for payment events
- 🛡️ **Secure Authentication** - API key and email-based authentication
- 📝 **Comprehensive Logging** - Debug with detailed request/response logs
- ⚡ **Exception Handling** - Graceful error handling with specific exceptions

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- Guzzle HTTP Client 7.x

## Installation

Install the package via Composer:

```bash
composer require paynecta/paynecta-laravel-sdk
```

### Publish Configuration

Publish the configuration file to customize settings:

```bash
php artisan vendor:publish --tag=paynecta-config
```

This will create a `config/paynecta.php` file in your Laravel application.

### Environment Configuration

Add your Paynecta credentials to your `.env` file:

```env
PAYNECTA_API_KEY=your_api_key_here
PAYNECTA_EMAIL=your-email@example.com
```

> **⚠️ Security Note:** Never commit your API credentials to version control. Always use environment variables.

## Getting Your API Credentials

1. Log in to your [Paynecta Dashboard](https://paynecta.co.ke)
2. Navigate to `/api` section
3. Click "Create New API Key"
4. Copy your API key immediately (it's shown only once!)
5. Store it securely in your `.env` file

## Quick Start

### Verify Authentication

Test your credentials and retrieve user information:

```php
use Paynecta\LaravelSdk\Facades\Paynecta;

try {
    $user = Paynecta::verifyAuth();
    
    echo "Authentication successful!";
    echo "User: " . $user['data']['email'];
    echo "Name: " . $user['data']['kyc']['first_name'];
    
} catch (\Paynecta\LaravelSdk\Exceptions\AuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage();
}
```

### Using Dependency Injection

You can also inject the client directly into your controllers:

```php
use Paynecta\LaravelSdk\PaynectaClient;

class PaymentController extends Controller
{
    public function __construct(
        protected PaynectaClient $paynecta
    ) {}
    
    public function verifyCredentials()
    {
        try {
            $user = $this->paynecta->verifyAuth();
            return response()->json($user);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
```

## Configuration

The package uses the following configuration options (in `config/paynecta.php`):

```php
return [
    // Your API credentials
    'api_key' => env('PAYNECTA_API_KEY'),
    'email' => env('PAYNECTA_EMAIL'),
    
    // API endpoint (usually no need to change)
    'base_url' => env('PAYNECTA_BASE_URL', 'https://paynecta.co.ke/api/v1'),
    
    // Request timeout in seconds
    'timeout' => env('PAYNECTA_TIMEOUT', 30),
    
    // Enable request/response logging for debugging
    'logging' => env('PAYNECTA_LOGGING', false),
    
    // Laravel log channel to use
    'log_channel' => env('PAYNECTA_LOG_CHANNEL', 'stack'),
];
```

## Advanced Usage

### Custom Timeout

Set a custom timeout for long-running requests:

```php
use Paynecta\LaravelSdk\Facades\Paynecta;

$user = Paynecta::setTimeout(60)->verifyAuth();
```

### Enable Logging

Enable detailed logging for debugging:

```php
use Paynecta\LaravelSdk\Facades\Paynecta;

$user = Paynecta::setLogging(true)->verifyAuth();
```

Or set it in your `.env`:

```env
PAYNECTA_LOGGING=true
```

### Custom Base URL (Testing)

For testing or sandbox environments:

```php
use Paynecta\LaravelSdk\Facades\Paynecta;

$client = Paynecta::setBaseUrl('https://sandbox.paynecta.co.ke/api/v1');
```

## Exception Handling

The SDK throws specific exceptions for different error types:

```php
use Paynecta\LaravelSdk\Facades\Paynecta;
use Paynecta\LaravelSdk\Exceptions\AuthenticationException;
use Paynecta\LaravelSdk\Exceptions\ValidationException;
use Paynecta\LaravelSdk\Exceptions\NotFoundException;
use Paynecta\LaravelSdk\Exceptions\RateLimitException;
use Paynecta\LaravelSdk\Exceptions\PaynectaException;

try {
    $result = Paynecta::verifyAuth();
    
} catch (AuthenticationException $e) {
    // Invalid API key or email (401)
    $errorCode = $e->getErrorCode();
    $responseBody = $e->getResponseBody();
    
} catch (ValidationException $e) {
    // Invalid request data (400)
    
} catch (NotFoundException $e) {
    // Resource not found (404)
    
} catch (RateLimitException $e) {
    // Too many requests (429)
    
} catch (PaynectaException $e) {
    // Any other Paynecta error
    
} catch (\Exception $e) {
    // Network or other errors
}
```

### Exception Methods

All Paynecta exceptions provide these methods:

```php
$exception->getMessage();      // Human-readable error message
$exception->getCode();         // HTTP status code
$exception->getErrorCode();    // API-specific error code
$exception->getResponseBody(); // Full API response body
$exception->hasErrorCode();    // Check if error code exists
```

## API Reference

### Authentication

#### Verify Authentication

```php
Paynecta::verifyAuth(): array
```

Verifies your API credentials and returns user information.

**Response:**
```php
[
    'success' => true,
    'message' => 'Authentication successful',
    'data' => [
        'user_id' => 1,
        'email' => 'user@example.com',
        'api_access' => 'granted',
        'kyc' => [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'business_phone_number' => '+254700000000'
        ]
    ]
]
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email hello@paynecta.co.ke instead of using the issue tracker.

## Credits

- [Paynecta](https://github.com/Paynecta)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

- 📧 Email: hello@paynecta.co.ke
- 🌐 Website: [https://paynecta.co.ke](https://paynecta.co.ke)
- 📖 Documentation: [https://paynecta.co.ke/docs](https://paynecta.co.ke/docs)
- 💬 Support: [https://paynecta.co.ke/support](https://paynecta.co.ke/support)

---

Made with ❤️ by [Paynecta](https://paynecta.co.ke)