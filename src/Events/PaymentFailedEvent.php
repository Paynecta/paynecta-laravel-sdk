<?php

namespace Paynecta\LaravelSdk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailedEvent
{
    use Dispatchable, SerializesModels;

    public array $payload;
    public string $transactionReference;
    public float $amount;
    public string $reason;
    public string $mobileNumber;
    public int $resultCode;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
        $this->transactionReference = $payload['data']['transaction']['reference'] ?? '';
        $this->amount = (float) ($payload['data']['transaction']['amount'] ?? 0);
        $this->reason = $payload['data']['reason'] ?? '';
        $this->mobileNumber = $payload['data']['customer']['mobile_number'] ?? '';
        $this->resultCode = $payload['data']['result_code'] ?? 0;
    }
}