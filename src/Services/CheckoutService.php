<?php

namespace Paynecta\LaravelSdk\Services;

use Paynecta\LaravelSdk\Exceptions\ValidationException;
use Paynecta\LaravelSdk\PaynectaClient;

/**
 * Opening a checkout for a customer, and asking what became of it.
 *
 * There is no method here that takes a phone number, and there will not be
 * one. Until 2.0 this package sent a caller-supplied mobile_number to
 * /payment/initialize and a prompt went to whatever it was given. Around
 * fifteen thousand unwanted prompts went out that way and the shortcode was
 * flagged for it, which is why the API has no such field any more.
 *
 * What replaces it: you open a session for an amount, send the payer to the
 * address it returns, and they enter their own number on Paynecta's page and
 * prove it with a code before anything reaches their phone. A number typed
 * into your application is a number your application typed.
 */
class CheckoutService
{
    public function __construct(protected PaynectaClient $client) {}

    /**
     * Open a checkout and get the address to send the payer to.
     *
     * @param  int  $amount  In cents. 100000 is KES 1,000. Integers, always:
     *                       a float that is nearly 1245.00 is somebody's
     *                       money being nearly right.
     * @param  array{
     *     tapflow?: string,
     *     reference?: string,
     *     redirect_url?: string,
     *     cancel_url?: string,
     *     frame_origins?: array<int, string>
     * }  $options
     *
     * `reference` is your own handle for the sale, usually an order number.
     * It comes back on the payment and in every webhook about it, and it is
     * what you should match on: Paynecta's own reference is a string you
     * have never seen before it arrives.
     *
     * `frame_origins` are the sites allowed to embed this checkout. Leaving
     * it out means it cannot be framed at all, and that default is the
     * important half.
     */
    public function open(int $amount, array $options = []): array
    {
        if ($amount <= 0) {
            throw new ValidationException('An amount has to be a positive number of cents.');
        }

        $tapflow = $options['tapflow'] ?? config('paynecta.tapflow');
        if (! is_string($tapflow) || trim($tapflow) === '') {
            throw new ValidationException(
                'Which page is this payment for? Pass tapflow, or set PAYNECTA_TAPFLOW in your .env.'
            );
        }

        $body = array_filter([
            'tapflow' => trim($tapflow),
            'amount' => $amount,
            'reference' => $options['reference'] ?? null,
            'redirect_url' => $options['redirect_url'] ?? null,
            'cancel_url' => $options['cancel_url'] ?? null,
            'frame_origins' => $options['frame_origins'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        // Sent as a real array even when empty, because array_filter above
        // would drop it and "no origins" is a statement rather than an
        // omission.
        if (isset($options['frame_origins'])) {
            $body['frame_origins'] = array_values($options['frame_origins']);
        }

        return $this->client->post('/v1/checkout_sessions', $body);
    }

    /**
     * What happened to a checkout.
     *
     * This, or a webhook, is how you learn the outcome. A payer arriving at
     * your redirect proves nothing, because anybody can type that address.
     */
    public function find(string $id): array
    {
        return $this->client->get('/v1/checkout_sessions/' . rawurlencode($id));
    }

    /**
     * Whether the money actually arrived.
     *
     * `payment` is absent until the payer has started one, rather than
     * present and empty, so this checks for it before reading a status.
     */
    public function settled(string $id): bool
    {
        $session = $this->find($id);

        return ($session['payment']['status'] ?? null) === 'succeeded';
    }
}
