<?php

namespace Paynecta\LaravelSdk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * What every payment event carries.
 *
 * The payment object exactly as the API returns it, plus named accessors for
 * the handful of fields nearly every listener reaches for.
 *
 * `payload` is kept whole rather than flattened into properties, because the
 * API grows fields and a listener that needs a new one should not have to
 * wait for a release of this package.
 */
abstract class PaymentEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly array $payload) {}

    /** Paynecta's handle, shared by the payer, you and Safaricom. */
    public function reference(): ?string
    {
        return $this->payload['reference'] ?? null;
    }

    /**
     * Your own handle for the sale, from the checkout you opened.
     *
     * Match on this. Paynecta's reference is a string your application has
     * never seen before the delivery arrives, and matching on amount and
     * time goes wrong the day two customers buy the same thing a minute
     * apart.
     */
    public function merchantReference(): ?string
    {
        return $this->payload['merchant_reference'] ?? null;
    }

    /** Cents. 124500 is KES 1,245.00. */
    public function amount(): int
    {
        return (int) ($this->payload['amount'] ?? 0);
    }

    public function currency(): string
    {
        return (string) ($this->payload['currency'] ?? 'KES');
    }

    /** M-Pesa's receipt. Absent until a payment has settled. */
    public function receipt(): ?string
    {
        return $this->payload['receipt'] ?? null;
    }

    /**
     * The payer, masked.
     *
     * Masked is all the API returns and all this needs to be: enough to
     * recognise the customer ringing about their order, not enough to be a
     * list of numbers if a log is scraped.
     */
    public function payer(): ?string
    {
        return $this->payload['payer'] ?? null;
    }

    /** `succeeded`, `pending`, or one of the ways a payment can fail. */
    public function status(): ?string
    {
        return $this->payload['status'] ?? null;
    }

    /** Why it did not work, in the provider's words. */
    public function detail(): ?string
    {
        return $this->payload['detail'] ?? null;
    }

    /** Which integration took it, when a registered one did. */
    public function integration(): ?string
    {
        return $this->payload['integration'] ?? null;
    }
}
