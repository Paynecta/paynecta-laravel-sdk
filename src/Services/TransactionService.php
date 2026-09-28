<?php

namespace Paynecta\LaravelSdk\Services;

use Paynecta\LaravelSdk\PaynectaClient;

/** The payments this business has taken. */
class TransactionService
{
    public function __construct(protected PaynectaClient $client) {}

    /**
     * @param  array{
     *     page?: int, per_page?: int, status?: string,
     *     from?: string, to?: string, search?: string, tapflow?: string
     * }  $filters
     */
    public function all(array $filters = []): array
    {
        return $this->client->get('/v1/transactions', $filters);
    }

    /** One payment, by the reference a payer, a merchant and Safaricom share. */
    public function find(string $reference): array
    {
        return $this->client->get('/v1/transactions/' . rawurlencode($reference));
    }
}
