<?php

namespace Paynecta\LaravelSdk\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Paynecta\LaravelSdk\Events\PaymentFailedEvent;
use Paynecta\LaravelSdk\Events\PaymentPendingEvent;
use Paynecta\LaravelSdk\Events\PaymentSettledEvent;
use Paynecta\LaravelSdk\Exceptions\WebhookException;

/**
 * Deliveries arriving from Paynecta, verified before they are believed.
 *
 * Until 2.0 nothing here was verified. validateWebhook() checked that five
 * fields were present and the body was then acted on, so anybody who knew
 * the URL — a fixed default path — could post a payment.completed and have
 * an order marked paid. That is the bug this class exists to have fixed.
 *
 * The scheme is published at docs.paynecta.co.ke/api/webhooks: HMAC-SHA256
 * over the timestamp, a full stop, then the exact bytes of the body, hex
 * encoded, inside five minutes.
 */
class WebhookService
{
    public const HEADER_SIGNATURE = 'Paynecta-Signature';
    public const HEADER_TIMESTAMP = 'Paynecta-Timestamp';
    public const HEADER_EVENT = 'Paynecta-Event';
    public const HEADER_DELIVERY = 'Paynecta-Delivery';

    /**
     * Handle one delivery.
     *
     * @throws WebhookException when it cannot be trusted. The controller
     *                          turns that into a 401, which is the honest
     *                          answer: we could not tell who sent this.
     */
    public function handle(Request $request): array
    {
        $this->verify($request);

        $payload = $request->json()->all();
        $event = (string) ($payload['event'] ?? $request->header(self::HEADER_EVENT, ''));
        $delivery = (string) $request->header(self::HEADER_DELIVERY, '');

        // Accepted and acted on in no way at all. A test send is somebody
        // pressing a button in their dashboard; treating it as news about
        // money would mark an order paid from the settings screen.
        if ($event === 'webhook.test') {
            return ['handled' => false, 'event' => $event, 'reason' => 'test'];
        }

        // We would rather tell you twice than not at all, so repeats are
        // expected. The cache is a convenience and not a ledger: clear it
        // and a repeat gets through, which is why your own listener should
        // still be safe to run twice.
        if ($delivery !== '' && $this->alreadySeen($delivery)) {
            return ['handled' => false, 'event' => $event, 'reason' => 'duplicate'];
        }

        $payment = $payload['data'] ?? [];

        match ($event) {
            'payment.settled' => event(new PaymentSettledEvent($payment)),
            'payment.failed' => event(new PaymentFailedEvent($payment)),
            'payment.pending' => event(new PaymentPendingEvent($payment)),
            default => $this->unknown($event, $payment),
        };

        if ($delivery !== '') {
            $this->remember($delivery);
        }

        return ['handled' => true, 'event' => $event];
    }

    /**
     * Whether this really came from Paynecta.
     *
     * @throws WebhookException
     */
    public function verify(Request $request): void
    {
        $secret = (string) config('paynecta.webhook_secret', '');

        // Fails closed, deliberately. An endpoint with no secret cannot
        // tell us from a stranger, and the alternative — accepting anything
        // while unconfigured — is exactly the hole this replaces.
        if ($secret === '') {
            throw new WebhookException(
                'No webhook signing secret is set, so deliveries cannot be verified. '
                . 'Register your address with Paynecta and put the secret it returns in PAYNECTA_WEBHOOK_SECRET.'
            );
        }

        $signature = trim((string) $request->header(self::HEADER_SIGNATURE, ''));
        $timestamp = trim((string) $request->header(self::HEADER_TIMESTAMP, ''));

        if ($signature === '' || $timestamp === '' || ! ctype_digit($timestamp)) {
            throw new WebhookException('This delivery carries no usable signature headers.');
        }

        $tolerance = (int) config('paynecta.webhook_tolerance', 300);
        if (abs(time() - (int) $timestamp) > $tolerance) {
            // A signature does not expire on its own, so without a window a
            // delivery captured once could be replayed for ever.
            throw new WebhookException('This delivery is too old to accept.');
        }

        // The raw body, byte for byte. Re-encoding a decoded payload would
        // verify a different string from the one that was signed, which is
        // the single most common way a verification routine comes to pass
        // everything.
        $expected = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret);

        // Constant time. A plain comparison returns sooner the earlier it
        // finds a difference, and that timing is enough to build a valid
        // signature one character at a time.
        if (! hash_equals($expected, $signature)) {
            throw new WebhookException('This delivery was not signed with your secret.');
        }
    }

    protected function alreadySeen(string $delivery): bool
    {
        if (! config('paynecta.prevent_duplicates', true)) {
            return false;
        }

        return Cache::has($this->deliveryKey($delivery));
    }

    protected function remember(string $delivery): void
    {
        if (! config('paynecta.prevent_duplicates', true)) {
            return;
        }

        Cache::put(
            $this->deliveryKey($delivery),
            true,
            (int) config('paynecta.duplicate_window', 86400)
        );
    }

    protected function deliveryKey(string $delivery): string
    {
        return 'paynecta.delivery.' . sha1($delivery);
    }

    /**
     * An event we do not know.
     *
     * Logged and accepted rather than refused. A new event name is us adding
     * something, not a stranger forging something: it arrived with a valid
     * signature, and answering with an error would put a legitimate delivery
     * in the merchant's failed list and eventually get the address switched
     * off.
     */
    protected function unknown(string $event, array $payment): void
    {
        Log::channel(config('paynecta.log_channel'))->info(
            'Paynecta sent an event this package does not handle',
            ['event' => $event, 'reference' => $payment['reference'] ?? null]
        );
    }
}
