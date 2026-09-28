<?php

namespace Paynecta\LaravelSdk\Services;

use Paynecta\LaravelSdk\PaynectaClient;

/**
 * What is left to spend with us.
 *
 * Our fee on each payment and every verification message come out of it, so
 * this is the question a merchant's own system asks before it tries to
 * collect.
 */
class BalanceService
{
    public function __construct(protected PaynectaClient $client) {}

    public function get(): array
    {
        return $this->client->get('/v1/balance');
    }
}
