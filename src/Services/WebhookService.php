<?php

namespace Paynecta\LaravelSdk\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Paynecta\LaravelSdk\Events\PaymentCompletedEvent;
use Paynecta\LaravelSdk\Events\PaymentFailedEvent;
use Paynecta\LaravelSdk\Events\PaymentCancelledEvent;

class WebhookService
{
    /**
     * Handle incoming webhook request
     * 
     * @param Request $request
     * @return array
     */
    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();
        
        // Log webhook if logging is enabled
        if (config('paynecta.logging')) {
            $this->logWebhook($payload);
        }
        
        // Validate webhook payload
        if (!$this->validateWebhook($payload)) {
            return [
                'success' => false,
                'message' => 'Invalid webhook payload'
            ];
        }
        
        // Check for duplicate events
        if ($this->isDuplicateEvent($payload['event_id'])) {
            return [
                'success' => true,
                'message' => 'Event already processed'
            ];
        }
        
        // Process webhook based on event type
        $this->processWebhook($payload);
        
        return [
            'success' => true,
            'message' => 'Webhook processed successfully'
        ];
    }
    
    /**
     * Validate webhook payload structure
     * 
     * @param array $payload
     * @return bool
     */
    protected function validateWebhook(array $payload): bool
    {
        $requiredFields = ['event_type', 'event_id', 'timestamp', 'link_id', 'data'];
        
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if event has already been processed
     * 
     * @param string $eventId
     * @return bool
     */
    protected function isDuplicateEvent(string $eventId): bool
    {
        // Check if duplicate detection is enabled
        if (!config('paynecta.webhook_duplicate_detection', true)) {
            return false;
        }
        
        // Use cache to track processed events (store for 24 hours)
        $cacheKey = "paynecta_webhook_event_{$eventId}";
        
        if (cache()->has($cacheKey)) {
            return true;
        }
        
        // Mark event as processed
        cache()->put($cacheKey, true, now()->addDay());
        
        return false;
    }
    
    /**
     * Process webhook based on event type
     * 
     * @param array $payload
     * @return void
     */
    protected function processWebhook(array $payload): void
    {
        $eventType = $payload['event_type'];
        
        match ($eventType) {
            'payment.completed' => $this->handlePaymentCompleted($payload),
            'payment.failed' => $this->handlePaymentFailed($payload),
            'payment.cancelled' => $this->handlePaymentCancelled($payload),
            default => $this->handleUnknownEvent($payload)
        };
    }
    
    /**
     * Handle payment completed webhook
     * 
     * @param array $payload
     * @return void
     */
    protected function handlePaymentCompleted(array $payload): void
    {
        event(new PaymentCompletedEvent($payload));
        
        if (config('paynecta.logging')) {
            Log::channel(config('paynecta.log_channel', 'stack'))
                ->info('Paynecta: Payment Completed', [
                    'reference' => $payload['data']['transaction']['reference'] ?? null,
                    'amount' => $payload['data']['transaction']['amount'] ?? null,
                    'receipt' => $payload['data']['MpesaReceiptNumber'] ?? null
                ]);
        }
    }
    
    /**
     * Handle payment failed webhook
     * 
     * @param array $payload
     * @return void
     */
    protected function handlePaymentFailed(array $payload): void
    {
        event(new PaymentFailedEvent($payload));
        
        if (config('paynecta.logging')) {
            Log::channel(config('paynecta.log_channel', 'stack'))
                ->warning('Paynecta: Payment Failed', [
                    'reference' => $payload['data']['transaction']['reference'] ?? null,
                    'reason' => $payload['data']['reason'] ?? null
                ]);
        }
    }
    
    /**
     * Handle payment cancelled webhook
     * 
     * @param array $payload
     * @return void
     */
    protected function handlePaymentCancelled(array $payload): void
    {
        event(new PaymentCancelledEvent($payload));
        
        if (config('paynecta.logging')) {
            Log::channel(config('paynecta.log_channel', 'stack'))
                ->info('Paynecta: Payment Cancelled', [
                    'reference' => $payload['data']['transaction']['reference'] ?? null,
                    'reason' => $payload['data']['reason'] ?? null
                ]);
        }
    }
    
    /**
     * Handle unknown event type
     * 
     * @param array $payload
     * @return void
     */
    protected function handleUnknownEvent(array $payload): void
    {
        if (config('paynecta.logging')) {
            Log::channel(config('paynecta.log_channel', 'stack'))
                ->warning('Paynecta: Unknown webhook event type', [
                    'event_type' => $payload['event_type'] ?? 'unknown'
                ]);
        }
    }
    
    /**
     * Log webhook payload
     * 
     * @param array $payload
     * @return void
     */
    protected function logWebhook(array $payload): void
    {
        Log::channel(config('paynecta.log_channel', 'stack'))
            ->info('Paynecta Webhook Received', [
                'event_type' => $payload['event_type'] ?? null,
                'event_id' => $payload['event_id'] ?? null,
                'timestamp' => $payload['timestamp'] ?? null,
                'payload' => $payload
            ]);
    }
    
    /**
     * Extract transaction reference from payload
     * 
     * @param array $payload
     * @return string|null
     */
    public function getTransactionReference(array $payload): ?string
    {
        return $payload['data']['transaction']['reference'] ?? null;
    }
    
    /**
     * Extract transaction amount from payload
     * 
     * @param array $payload
     * @return float|null
     */
    public function getAmount(array $payload): ?float
    {
        $amount = $payload['data']['transaction']['amount'] ?? null;
        return $amount ? (float) $amount : null;
    }
    
    /**
     * Extract M-Pesa receipt number from payload (completed payments only)
     * 
     * @param array $payload
     * @return string|null
     */
    public function getMpesaReceiptNumber(array $payload): ?string
    {
        return $payload['data']['MpesaReceiptNumber'] ?? null;
    }
    
    /**
     * Extract customer mobile number from payload
     * 
     * @param array $payload
     * @return string|null
     */
    public function getCustomerMobile(array $payload): ?string
    {
        return $payload['data']['customer']['mobile_number'] ?? null;
    }
    
    /**
     * Extract failure/cancellation reason from payload
     * 
     * @param array $payload
     * @return string|null
     */
    public function getReason(array $payload): ?string
    {
        return $payload['data']['reason'] ?? null;
    }
    
    /**
     * Get transaction status from payload
     * 
     * @param array $payload
     * @return string|null
     */
    public function getStatus(array $payload): ?string
    {
        return $payload['data']['transaction']['status'] ?? null;
    }
    
    /**
     * Check if webhook is for completed payment
     * 
     * @param array $payload
     * @return bool
     */
    public function isPaymentCompleted(array $payload): bool
    {
        return ($payload['event_type'] ?? null) === 'payment.completed';
    }
    
    /**
     * Check if webhook is for failed payment
     * 
     * @param array $payload
     * @return bool
     */
    public function isPaymentFailed(array $payload): bool
    {
        return ($payload['event_type'] ?? null) === 'payment.failed';
    }
    
    /**
     * Check if webhook is for cancelled payment
     * 
     * @param array $payload
     * @return bool
     */
    public function isPaymentCancelled(array $payload): bool
    {
        return ($payload['event_type'] ?? null) === 'payment.cancelled';
    }
}