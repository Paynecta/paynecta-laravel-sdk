<?php

namespace Paynecta\LaravelSdk\Services;

use Paynecta\LaravelSdk\PaynectaClient;

class CurrencyRatesService
{
    public function __construct(
        protected PaynectaClient $client
    ) {}

    public function getAll(): array
    {
        return $this->client->get('currency-rates');
    }

    public function get(string $currency): array
    {
        return $this->client->get("currency-rates/{$currency}");
    }

    public function convert(float $amount, string $from, string $to, ?string $date = null): array
    {
        $data = compact('amount', 'from', 'to');
        if ($date) {
            $data['date'] = $date;
        }
        
        return $this->client->post('currency-rates/convert', $data);
    }

    public function getHistory(string $from, string $to, string $startDate, string $endDate): array
    {
        $params = [
            'from' => $from,
            'to' => $to,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];

        return $this->client->get('currency-rates/history?' . http_build_query($params));
    }

    public function getRate(array $response): float
    {
        return $response['data']['rate'] ?? 0.0;
    }

    public function getConvertedAmount(array $response): float
    {
        return $response['data']['converted_amount'] ?? 0.0;
    }

    public function getRates(array $response): array
    {
        return $response['data']['rates'] ?? [];
    }
}
