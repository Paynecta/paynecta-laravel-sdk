<?php

namespace Paynecta\LaravelSdk\Services;

use Paynecta\LaravelSdk\PaynectaClient;

/**
 * This application, as it appears in the Paynecta dashboard.
 *
 * Somebody who installs this package has no way of telling, from the
 * dashboard, that it worked: keys say something could call us, payments say
 * something did, and nothing in between says the application is connected
 * and last spoke an hour ago. That is the first thing anybody checks when a
 * payment has not appeared.
 *
 * Registering also marks the payments. A checkout opened on a key with a
 * registered integration carries it, so one account running a shop and a
 * market stall can tell the takings apart.
 */
class IntegrationService
{
    public function __construct(protected PaynectaClient $client) {}

    /** Every application registered against this account. */
    public function all(): array
    {
        return $this->client->get('/v1/integrations');
    }

    /**
     * Say this application is here, or that it still is.
     *
     * Idempotent on the site address, so calling it on a schedule updates
     * one row rather than adding one per call. Whether an integration reads
     * as connected is computed from when it last called, not from a flag:
     * a flag would have to be switched off by the thing that stopped
     * working, which is precisely the thing that cannot be relied on to do
     * it.
     *
     * Worth calling on deploy, and then daily.
     */
    public function register(array $overrides = []): array
    {
        return $this->client->put('/v1/integrations', array_merge([
            // Not 'laravel'. The API takes a short closed list, and an
            // unrecognised kind is refused rather than quietly relabelled.
            'kind' => 'custom',
            'site' => config('app.url'),
            'name' => config('app.name'),
            'version' => \Paynecta\LaravelSdk\PaynectaServiceProvider::VERSION,
            'platform_version' => app()->version(),
            'host_version' => PHP_VERSION,
        ], $overrides));
    }

    /** Remove one, for an application that is being retired. */
    public function forget(string $id): array
    {
        return $this->client->delete('/v1/integrations/' . rawurlencode($id));
    }
}
