<?php

namespace Paynecta\LaravelSdk\Services;

use Paynecta\LaravelSdk\PaynectaClient;

/**
 * The pages this business collects through.
 *
 * Every checkout names one, so listing them is usually the first call
 * anybody integrating makes.
 */
class TapFlowService
{
    public function __construct(protected PaynectaClient $client) {}

    /**
     * @param  bool  $usableOnly  Narrows the list to pages a checkout can
     *                            actually be opened on. A page that names
     *                            its own fixed price cannot take an amount
     *                            from your order, so offering it in a picker
     *                            is offering a choice that fails later at
     *                            somebody's checkout.
     */
    public function all(bool $usableOnly = false, array $filters = []): array
    {
        if ($usableOnly) {
            $filters['usable'] = 'true';
        }

        return $this->client->get('/v1/tapflows', $filters);
    }

    /**
     * @param  array{slug?: string, description?: string, fixed_amount?: int}  $options
     */
    public function create(string $name, array $options = []): array
    {
        return $this->client->post('/v1/tapflows', array_filter([
            'name' => $name,
            'slug' => $options['slug'] ?? null,
            'description' => $options['description'] ?? null,
            'fixed_amount' => $options['fixed_amount'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));
    }
}
