# Paynecta Laravel SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/paynecta/paynecta-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/paynecta/paynecta-laravel-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/paynecta/paynecta-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/paynecta/paynecta-laravel-sdk)
[![License](https://img.shields.io/packagist/l/paynecta/paynecta-laravel-sdk.svg?style=flat-square)](https://packagist.org/packages/paynecta/paynecta-laravel-sdk)

Official Laravel SDK for [Paynecta Payment API](https://paynecta.co.ke) - Simplify M-Pesa, Bank, and Wallet payment integrations in Kenya.

## Features

- 🚀 **Easy Integration** - Get started in minutes with simple configuration
- 💳 **M-Pesa STK Push** - Trigger payment prompts directly on customer phones
- 💰 **Payment Links** - Create and manage shareable payment pages
- 🏦 **Bank Integration** - Access all available banks and their paybill numbers
- 🔔 **Real-time Webhooks** - Instant notifications for payment events
- 🛡️ **Secure Authentication** - API key and email-based authentication
- 📝 **Comprehensive Logging** - Debug with detailed request/response logs
- ⚡ **Exception Handling** - Graceful error handling with specific exceptions
- 🎯 **Laravel Events** - Native Laravel event system for webhooks

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
PAYNECTA_LOGGING=false
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
    
} catch (\Paynecta\LaravelSdk\Exceptions\AuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage();
}
```

## Usage Guide

### 1. Payment Links

```php
use Paynecta\LaravelSdk\Facades\Paynecta;

// Get all payment links
$links = Paynecta::paymentLinks()->getAll();

// Get specific link
$link = Paynecta::paymentLinks()->get('ABC123');

// Get only invoices
$invoices = Paynecta::paymentLinks()->getInvoices();

// Search links
$results = Paynecta::paymentLinks()->search('subscription');

// Count total links
$count = Paynecta::paymentLinks()->count();
```

### 2. Initialize Payments (M-Pesa STK Push)

```php
use Paynecta\LaravelSdk\Facades\Paynecta;

// Initialize payment
$payment = Paynecta::payments()->initialize(
    'ABC123',           // Payment link code
    '254700000000',     // Mobile number
    100                 // Amount in KES (1-70,000)
);

// With validation
$payment = Paynecta::payments()->initializeWithValidation(
    'ABC123',
    '0700000000',       // Accepts 07XX format
    500
);

// Get transaction reference
$reference = Paynecta::payments()->getTransactionReference($payment);
echo "Transaction Reference: {$reference}";
```

### 3. Query Payment Status

```php
use Paynecta\LaravelSdk\Facades\Paynecta;

// Query status
$status = Paynecta::payments()->queryStatus('ABCP20240803123456ABCD');

// Check status
if (Paynecta::payments()->isCompleted($status)) {
    $receipt = Paynecta::payments()->getMpesaReceiptNumber($status);
    echo "Payment completed! Receipt: {$receipt}";
}

if (Paynecta::payments()->isFailed($status)) {
    $reason = Paynecta::payments()->getFailureReason($status);
    echo "Payment failed: {$reason}";
}

// Poll until complete (checks every 2 seconds, max 30 attempts)
$finalStatus = Paynecta::payments()->pollStatus($reference, 30, 2);
```

### 4. Currency Rates

```php
use Paynecta\LaravelSdk\Facades\Paynecta;

// Get all currency rates (160+ currencies)
$rates = Paynecta::currencyRates()->getAll();

// Get specific currency rate
$usdRate = Paynecta::currencyRates()->get('USD');

// Convert currency
$converted = Paynecta::currencyRates()->convert(100, 'USD', 'KES');

// Convert with specific date
$converted = Paynecta::currencyRates()->convert(100, 'USD', 'KES', '2025-10-01');

// Get historical rates
$history = Paynecta::currencyRates()->getHistory('KES', 'USD', '2025-10-01', '2025-10-13');

// Helper methods
$rate = Paynecta::currencyRates()->getRate($usdRate);
$amount = Paynecta::currencyRates()->getConvertedAmount($converted);
$allRates = Paynecta::currencyRates()->getRates($rates);
```

### 5. Banks

```php
use Paynecta\LaravelSdk\Facades\Paynecta;

// Get all banks
$banks = Paynecta::banks()->getAll();

// Get specific bank
$bank = Paynecta::banks()->get('jR3kL9');

// Search banks
$results = Paynecta::banks()->search('equity');

// Find by name
$kcb = Paynecta::banks()->findByName('KCB Bank');

// Find by paybill
$bank = Paynecta::banks()->findByPaybill('247247');

// For dropdowns (returns bank_id => bank_name)
$options = Paynecta::banks()->getForDropdown();

// Simple list (returns bank_name => paybill_number)
$list = Paynecta::banks()->getAllAsList();

// Grouped by first letter
$grouped = Paynecta::banks()->getGroupedByLetter();
```

### 6. Webhooks

#### Setup Webhook URL

Your webhook URL will be automatically registered at:
```
https://yourdomain.com/paynecta/webhook
```

Add to your dashboard at: https://paynecta.co.ke

#### Whitelist Webhook Route (Important!)

Add to `app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'paynecta/webhook',
];
```

#### Listen to Webhook Events

Create event listeners:

```bash
php artisan make:listener HandlePaymentCompleted
```

In `app/Listeners/HandlePaymentCompleted.php`:

```php
<?php

namespace App\Listeners;

use Paynecta\LaravelSdk\Events\PaymentCompletedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandlePaymentCompleted implements ShouldQueue
{
    public function handle(PaymentCompletedEvent $event)
    {
        // Access payment data
        $reference = $event->transactionReference;
        $amount = $event->amount;
        $receipt = $event->mpesaReceiptNumber;
        $mobile = $event->mobileNumber;
        
        // Update database
        \DB::table('payments')
            ->where('transaction_reference', $reference)
            ->update([
                'status' => 'completed',
                'mpesa_receipt' => $receipt,
                'paid_at' => now()
            ]);
        
        // Send confirmation, fulfill order, etc.
    }
}
```

Register in `app/Providers/EventServiceProvider.php`:

```php
use Paynecta\LaravelSdk\Events\PaymentCompletedEvent;
use Paynecta\LaravelSdk\Events\PaymentFailedEvent;
use Paynecta\LaravelSdk\Events\PaymentCancelledEvent;

protected $listen = [
    PaymentCompletedEvent::class => [
        HandlePaymentCompleted::class,
    ],
    PaymentFailedEvent::class => [
        HandlePaymentFailed::class,
    ],
    PaymentCancelledEvent::class => [
        HandlePaymentCancelled::class,
    ],
];
```

## Complete Payment Flow Example

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Paynecta\LaravelSdk\Facades\Paynecta;
use App\Models\Order;

class CheckoutController extends Controller
{
    public function initiatePayment(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'mobile_number' => 'required|string',
        ]);
        
        $order = Order::findOrFail($validated['order_id']);
        
        try {
            // Initialize payment
            $payment = Paynecta::payments()->initializeWithValidation(
                'YOUR_LINK_CODE',
                $validated['mobile_number'],
                $order->total_amount
            );
            
            $reference = Paynecta::payments()->getTransactionReference($payment);
            
            // Save transaction reference
            $order->update([
                'transaction_reference' => $reference,
                'payment_status' => 'pending'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Check your phone for STK push',
                'transaction_reference' => $reference
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    public function checkStatus($reference)
    {
        try {
            $status = Paynecta::payments()->queryStatus($reference);
            
            return response()->json([
                'success' => true,
                'status' => Paynecta::payments()->getStatus($status),
                'is_completed' => Paynecta::payments()->isCompleted($status),
                'mpesa_receipt' => Paynecta::payments()->getMpesaReceiptNumber($status),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }
}
```

## Configuration Options

The package uses the following configuration options (in `config/paynecta.php`):

```php
return [
    // API credentials
    'api_key' => env('PAYNECTA_API_KEY'),
    'email' => env('PAYNECTA_EMAIL'),
    
    // API endpoint
    'base_url' => env('PAYNECTA_BASE_URL', 'https://paynecta.co.ke/api/v1'),
    
    // Request timeout in seconds
    'timeout' => env('PAYNECTA_TIMEOUT', 30),
    
    // Enable logging for debugging
    'logging' => env('PAYNECTA_LOGGING', false),
    
    // Log channel
    'log_channel' => env('PAYNECTA_LOG_CHANNEL', 'stack'),
    
    // Webhook settings
    'webhook_path' => env('PAYNECTA_WEBHOOK_PATH', 'paynecta/webhook'),
    'webhook_duplicate_detection' => env('PAYNECTA_WEBHOOK_DUPLICATE_DETECTION', true),
    'webhook_middleware' => ['api'],
];
```

## Advanced Usage

### Dependency Injection

```php
use Paynecta\LaravelSdk\PaynectaClient;

class PaymentController extends Controller
{
    public function __construct(
        protected PaynectaClient $paynecta
    ) {}
    
    public function processPayment()
    {
        $user = $this->paynecta->verifyAuth();
        $links = $this->paynecta->paymentLinks()->getAll();
    }
}
```

### Custom Timeout

```php
$user = Paynecta::setTimeout(60)->verifyAuth();
```

### Enable Logging

```php
// In .env
PAYNECTA_LOGGING=true

// Or programmatically
Paynecta::setLogging(true)->verifyAuth();
```

### Custom Base URL (Testing)

```php
Paynecta::setBaseUrl('https://sandbox.paynecta.co.ke/api/v1');
```

## Exception Handling

The SDK throws specific exceptions for different error types:

```php
use Paynecta\LaravelSdk\Exceptions\AuthenticationException;
use Paynecta\LaravelSdk\Exceptions\ValidationException;
use Paynecta\LaravelSdk\Exceptions\NotFoundException;
use Paynecta\LaravelSdk\Exceptions\RateLimitException;
use Paynecta\LaravelSdk\Exceptions\PaynectaException;

try {
    $result = Paynecta::payments()->initialize('ABC123', '254700000000', 100);
    
} catch (AuthenticationException $e) {
    // Invalid API key or email (401)
    $errorCode = $e->getErrorCode();
    $responseBody = $e->getResponseBody();
    
} catch (ValidationException $e) {
    // Invalid request data (400)
    $errors = $e->getResponseBody()['errors'] ?? [];
    
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

## Testing

### Testing Webhooks Locally

Use ngrok to expose your local server:

```bash
ngrok http 8000
```

Copy the HTTPS URL and add to your Paynecta dashboard:
```
https://abc123.ngrok.io/paynecta/webhook
```

### Manual Testing

```php
Route::get('/test-payment', function () {
    try {
        // Test authentication
        $user = Paynecta::verifyAuth();
        echo "✓ Authentication successful\n";
        
        // Test payment links
        $links = Paynecta::paymentLinks()->getAll();
        echo "✓ Found {$links['data']['total']} payment links\n";
        
        // Test banks
        $banks = Paynecta::banks()->getAll();
        echo "✓ Found {$banks['data']['total']} banks\n";
        
        return 'All tests passed!';
        
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
});
```

## API Reference

### Authentication
- `verifyAuth()` - Verify API credentials and get user info

### Payment Links
- `paymentLinks()->getAll()` - Get all payment links
- `paymentLinks()->get($code)` - Get specific link
- `paymentLinks()->getInvoices()` - Get invoice links only
- `paymentLinks()->getRegularLinks()` - Get non-invoice links
- `paymentLinks()->search($term)` - Search links
- `paymentLinks()->count()` - Get total count

### Payments
- `payments()->initialize($code, $mobile, $amount)` - Initialize STK push
- `payments()->initializeWithValidation($code, $mobile, $amount)` - Initialize with validation
- `payments()->queryStatus($reference)` - Query payment status
- `payments()->pollStatus($reference, $maxAttempts, $sleepSeconds)` - Poll until complete
- `payments()->isCompleted($response)` - Check if completed
- `payments()->isPending($response)` - Check if pending
- `payments()->isFailed($response)` - Check if failed
- `payments()->getMpesaReceiptNumber($response)` - Get receipt number
- `payments()->getFailureReason($response)` - Get failure reason

### Banks
- `banks()->getAll()` - Get all banks
- `banks()->get($bankId)` - Get specific bank
- `banks()->search($term)` - Search banks
- `banks()->findByName($name)` - Find by exact name
- `banks()->findByPaybill($paybill)` - Find by paybill number
- `banks()->getForDropdown()` - Get for select dropdown
- `banks()->getAllAsList()` - Get as simple list
- `banks()->getGroupedByLetter()` - Get grouped by first letter

### Currency Rates
- `currencyRates()->getAll()` - Get all currency rates (160+ currencies)
- `currencyRates()->get($currency)` - Get specific currency rate
- `currencyRates()->convert($amount, $from, $to, $date)` - Convert currency amounts
- `currencyRates()->getHistory($from, $to, $startDate, $endDate)` - Get historical rates
- `currencyRates()->getRate($response)` - Extract rate from response
- `currencyRates()->getConvertedAmount($response)` - Extract converted amount
- `currencyRates()->getRates($response)` - Extract rates array

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