<?php

namespace Paynecta\LaravelSdk\Services;

use Paynecta\LaravelSdk\PaynectaClient;

class BankService
{
    public function __construct(
        protected PaynectaClient $client
    ) {}

    /**
     * Get all available banks
     * 
     * Retrieve a complete list of all available banks with their paybill numbers
     * for payment processing. Banks are returned ordered alphabetically by name.
     * 
     * @return array
     * @throws \Paynecta\LaravelSdk\Exceptions\PaynectaException
     * 
     * @example
     * $banks = $bankService->getAll();
     * 
     * Response:
     * [
     *     'success' => true,
     *     'code' => 200,
     *     'message' => 'Banks retrieved successfully',
     *     'timestamp' => '2024-08-03T12:00:00.000Z',
     *     'data' => [
     *         'banks' => [
     *             [
     *                 'bank_id' => 'jR3kL9',
     *                 'bank_name' => 'Equity Bank',
     *                 'paybill_number' => '247247'
     *             ],
     *             ...
     *         ],
     *         'total' => 3
     *     ]
     * ]
     */
    public function getAll(): array
    {
        return $this->client->get('/banks');
    }

    /**
     * Get a specific bank by ID
     * 
     * Retrieve detailed information for a specific bank using its unique bank ID.
     * 
     * @param string $bankId Unique hashed identifier for the bank
     * @return array
     * @throws \Paynecta\LaravelSdk\Exceptions\NotFoundException
     * @throws \Paynecta\LaravelSdk\Exceptions\PaynectaException
     * 
     * @example
     * $bank = $bankService->get('jR3kL9');
     * 
     * Response:
     * [
     *     'success' => true,
     *     'code' => 200,
     *     'message' => 'Bank retrieved successfully',
     *     'data' => [
     *         'bank_id' => 'jR3kL9',
     *         'bank_name' => 'Equity Bank',
     *         'paybill_number' => '247247'
     *     ]
     * ]
     */
    public function get(string $bankId): array
    {
        return $this->client->get("/banks/{$bankId}");
    }

    /**
     * Get all banks as a simple list (bank_name => paybill_number)
     * 
     * @return array
     */
    public function getAllAsList(): array
    {
        $response = $this->getAll();
        $banks = $response['data']['banks'] ?? [];
        
        $list = [];
        foreach ($banks as $bank) {
            $list[$bank['bank_name']] = $bank['paybill_number'];
        }
        
        return $list;
    }

    /**
     * Get total count of available banks
     * 
     * @return int
     */
    public function count(): int
    {
        $response = $this->getAll();
        return $response['data']['total'] ?? 0;
    }

    /**
     * Check if any banks are available
     * 
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Search banks by name
     * 
     * @param string $search Search term (case-insensitive)
     * @return array
     */
    public function search(string $search): array
    {
        $response = $this->getAll();
        $banks = $response['data']['banks'] ?? [];
        
        $searchLower = strtolower($search);
        
        return array_filter($banks, function($bank) use ($searchLower) {
            $bankName = strtolower($bank['bank_name'] ?? '');
            return str_contains($bankName, $searchLower);
        });
    }

    /**
     * Find bank by exact name
     * 
     * @param string $bankName Bank name to search for
     * @return array|null Bank data or null if not found
     */
    public function findByName(string $bankName): ?array
    {
        $response = $this->getAll();
        $banks = $response['data']['banks'] ?? [];
        
        foreach ($banks as $bank) {
            if (strcasecmp($bank['bank_name'], $bankName) === 0) {
                return $bank;
            }
        }
        
        return null;
    }

    /**
     * Find bank by paybill number
     * 
     * @param string $paybillNumber Paybill number to search for
     * @return array|null Bank data or null if not found
     */
    public function findByPaybill(string $paybillNumber): ?array
    {
        $response = $this->getAll();
        $banks = $response['data']['banks'] ?? [];
        
        foreach ($banks as $bank) {
            if ($bank['paybill_number'] === $paybillNumber) {
                return $bank;
            }
        }
        
        return null;
    }

    /**
     * Get bank name from response
     * 
     * @param array $response Response from get() or element from getAll()
     * @return string|null
     */
    public function getBankName(array $response): ?string
    {
        return $response['data']['bank_name'] ?? $response['bank_name'] ?? null;
    }

    /**
     * Get paybill number from response
     * 
     * @param array $response Response from get() or element from getAll()
     * @return string|null
     */
    public function getPaybillNumber(array $response): ?string
    {
        return $response['data']['paybill_number'] ?? $response['paybill_number'] ?? null;
    }

    /**
     * Get bank ID from response
     * 
     * @param array $response Response from get() or element from getAll()
     * @return string|null
     */
    public function getBankId(array $response): ?string
    {
        return $response['data']['bank_id'] ?? $response['bank_id'] ?? null;
    }

    /**
     * Get banks grouped by first letter
     * 
     * @return array
     */
    public function getGroupedByLetter(): array
    {
        $response = $this->getAll();
        $banks = $response['data']['banks'] ?? [];
        
        $grouped = [];
        foreach ($banks as $bank) {
            $firstLetter = strtoupper(substr($bank['bank_name'], 0, 1));
            if (!isset($grouped[$firstLetter])) {
                $grouped[$firstLetter] = [];
            }
            $grouped[$firstLetter][] = $bank;
        }
        
        ksort($grouped);
        return $grouped;
    }

    /**
     * Get banks for dropdown/select options
     * Returns array with bank_id as key and bank_name as value
     * 
     * @return array
     */
    public function getForDropdown(): array
    {
        $response = $this->getAll();
        $banks = $response['data']['banks'] ?? [];
        
        $options = [];
        foreach ($banks as $bank) {
            $options[$bank['bank_id']] = $bank['bank_name'];
        }
        
        return $options;
    }
}