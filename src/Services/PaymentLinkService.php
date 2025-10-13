<?php

namespace Paynecta\LaravelSdk\Services;

use Paynecta\LaravelSdk\PaynectaClient;

class PaymentLinkService
{
    public function __construct(
        protected PaynectaClient $client
    ) {}

    /**
     * Get all payment links
     * 
     * Retrieve all payment links for your account.
     * 
     * @return array
     * @throws \Paynecta\LaravelSdk\Exceptions\PaynectaException
     * 
     * @example
     * $links = $paymentLinkService->getAll();
     * 
     * Response:
     * [
     *     'success' => true,
     *     'message' => 'Links retrieved successfully',
     *     'data' => [
     *         'links' => [
     *             [
     *                 'unique_code' => 'ABC123',
     *                 'name' => 'My Payment Link',
     *                 'display_name' => 'Payment for Services',
     *                 'slug' => 'my-payment-link',
     *                 'description' => 'Payment link description',
     *                 'is_invoice' => false,
     *                 'created_at' => '2024-08-03T12:00:00.000000Z',
     *                 'updated_at' => '2024-08-03T12:00:00.000000Z',
     *                 'payment_methods_count' => 2,
     *                 'has_customization' => true
     *             ]
     *         ],
     *         'total' => 5
     *     ]
     * ]
     */
    public function getAll(): array
    {
        return $this->client->get('/links');
    }

    /**
     * Get a specific payment link by unique code
     * 
     * @param string $uniqueCode The unique code of the payment link
     * @return array
     * @throws \Paynecta\LaravelSdk\Exceptions\NotFoundException
     * @throws \Paynecta\LaravelSdk\Exceptions\PaynectaException
     */
    public function get(string $uniqueCode): array
    {
        return $this->client->get("/links/{$uniqueCode}");
    }

    /**
     * Get total count of payment links
     * 
     * @return int
     */
    public function count(): int
    {
        $response = $this->getAll();
        return $response['data']['total'] ?? 0;
    }

    /**
     * Check if any payment links exist
     * 
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Filter payment links by type
     * 
     * @param bool $isInvoice Filter for invoice links (true) or regular links (false)
     * @return array
     */
    public function filterByType(bool $isInvoice): array
    {
        $response = $this->getAll();
        $links = $response['data']['links'] ?? [];
        
        return array_filter($links, function($link) use ($isInvoice) {
            return ($link['is_invoice'] ?? false) === $isInvoice;
        });
    }

    /**
     * Get only invoice links
     * 
     * @return array
     */
    public function getInvoices(): array
    {
        return $this->filterByType(true);
    }

    /**
     * Get only regular payment links (non-invoices)
     * 
     * @return array
     */
    public function getRegularLinks(): array
    {
        return $this->filterByType(false);
    }

    /**
     * Search payment links by name or display name
     * 
     * @param string $search Search term
     * @return array
     */
    public function search(string $search): array
    {
        $response = $this->getAll();
        $links = $response['data']['links'] ?? [];
        
        $searchLower = strtolower($search);
        
        return array_filter($links, function($link) use ($searchLower) {
            $name = strtolower($link['name'] ?? '');
            $displayName = strtolower($link['display_name'] ?? '');
            
            return str_contains($name, $searchLower) || 
                   str_contains($displayName, $searchLower);
        });
    }
}