<?php

namespace Paynecta\LaravelSdk\Services;

use Paynecta\LaravelSdk\Exceptions\ValidationException;
use Paynecta\LaravelSdk\PaynectaClient;

/**
 * Where Paynecta tells you what happened, set from your own application.
 *
 * Every integration used to end with the same paragraph: copy this address,
 * sign in, find the settings screen, paste it there. It is the one step that
 * happens in a different browser tab, and the one people skip — which leaves
 * an application taking payments and never hearing what became of them.
 *
 * Registering it from here removes the tab.
 *
 * Do not confuse this with WebhookService, which verifies the deliveries
 * that arrive. This one arranges for them.
 */
class WebhookEndpointService
{
    public function __construct(protected PaynectaClient $client) {}

    /** Every address this business has set, with the signing secret for each. */
    public function all(): array
    {
        return $this->client->get('/v1/webhooks');
    }

    /**
     * Point Paynecta at an address, and get the signing secret back.
     *
     * Adds the address beside any the business already has; it never
     * replaces one. Idempotent: calling it again with the same address keeps
     * the existing secret rather than rolling it, so re-running your setup
     * does not silently invalidate a secret already deployed.
     *
     * **Keep the secret it returns.** Put it in PAYNECTA_WEBHOOK_SECRET.
     * Without it this package refuses every delivery, which is the right
     * failure: an endpoint that cannot verify is an endpoint a stranger can
     * write payments into.
     *
     * @param  string|null  $tapflow  Scopes it to one page, for a business
     *                                running two shops that should hear
     *                                about their own takings separately.
     *                                Left out, it is the common address.
     */
    public function register(string $url, ?string $tapflow = null, ?bool $active = null): array
    {
        $url = trim($url);

        // Refused here rather than by the API, so the message names the
        // actual problem. https because a payment notice says what somebody
        // paid and when, and over plain http that is readable by anything
        // on the path.
        if (! preg_match('#^https://[^/\s]+#i', $url)) {
            throw new ValidationException(
                'A webhook address has to be a full https address, like https://yourapp.co.ke/paynecta/webhook.'
            );
        }

        $body = ['url' => $url];
        if ($tapflow !== null && trim($tapflow) !== '') {
            $body['tapflow'] = trim($tapflow);
        }
        if ($active !== null) {
            $body['active'] = $active;
        }

        return $this->client->put('/v1/webhooks', $body);
    }

    /**
     * Point Paynecta at this application's own webhook route.
     *
     * The address is built from the route this package registers, so it
     * cannot drift from where deliveries actually land. That is the whole
     * point: an address typed by hand is an address that stays right until
     * somebody changes a path.
     */
    public function registerSelf(?string $tapflow = null): array
    {
        return $this->register($this->ownUrl(), $tapflow);
    }

    /**
     * Stop deliveries to an address, without forgetting it.
     *
     * A business can have several addresses, so this names one: the URL you
     * pass, or this application's own webhook route when you pass none.
     */
    public function disable(?string $tapflow = null, ?string $url = null): array
    {
        return $this->register($url ?? $this->ownUrl(), $tapflow, false);
    }

    /**
     * Forget an address entirely.
     *
     * The URL you pass, or this application's own webhook route when you
     * pass none. Any other addresses the business has are untouched.
     */
    public function remove(?string $tapflow = null, ?string $url = null): array
    {
        // In the query string, which is where the API reads them. Sent as a
        // body, the TapFlow was ignored and the business's common address
        // was the one removed.
        $query = http_build_query(array_filter([
            'tapflow' => $tapflow,
            'url' => $url ?? $this->ownUrl(),
        ], fn ($value) => $value !== null && $value !== ''));

        return $this->client->delete('/v1/webhooks?'.$query);
    }

    /** Forget one address by the id GET /v1/webhooks lists it under. */
    public function removeById(string $id): array
    {
        return $this->client->delete('/v1/webhooks/'.rawurlencode($id));
    }

    /** Where this package receives deliveries. */
    protected function ownUrl(): string
    {
        return url(config('paynecta.webhook_path', 'paynecta/webhook'));
    }
}
