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
     * Idempotent: calling it again with the same address keeps the existing
     * secret rather than rolling it, so re-running your setup does not
     * silently invalidate a secret already deployed.
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
        return $this->register(
            url(config('paynecta.webhook_path', 'paynecta/webhook')),
            $tapflow
        );
    }

    /** Stop deliveries to an address, without forgetting it. */
    public function disable(?string $tapflow = null): array
    {
        $existing = $this->all();
        $url = $existing[0]['url'] ?? null;

        if (! is_string($url)) {
            throw new ValidationException('There is no webhook address set to disable.');
        }

        return $this->register($url, $tapflow, false);
    }

    /** Forget an address entirely. */
    public function remove(?string $tapflow = null): array
    {
        return $this->client->delete('/v1/webhooks', array_filter([
            'tapflow' => $tapflow,
        ], fn ($value) => $value !== null && $value !== ''));
    }
}
