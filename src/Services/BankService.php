<?php

namespace Paynecta\LaravelSdk\Services;

use Paynecta\LaravelSdk\PaynectaClient;

/**
 * The banks Paynecta can settle to.
 *
 * Reference data, whole rather than paged, because it is a list somebody
 * renders into a picker.
 */
class BankService
{
    public function __construct(protected PaynectaClient $client) {}

    public function all(): array
    {
        return $this->client->get('/v1/banks');
    }
}
