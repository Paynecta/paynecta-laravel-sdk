<?php

namespace Paynecta\LaravelSdk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompletedEvent
{
    use Dispatchable, SerializesModels;

    public array $payload;
    public string $transactionReference;
    public float $amount;
    public string $mpesaReceiptNumber;
    public string $mobileNumber;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
        $this->transactionReference = $payload['data']['transaction']['reference'] ?? '';
        $this->amount = (float) ($payload['data']['transaction']['amount'] ?? 0);
        $this->mpesaReceiptNumber = $payload['data']['MpesaReceiptNumber'] ?? '';
        $this->mobileNumber = $payload['data']['customer']['mobile_number'] ?? '';
    }
}