<?php

namespace Paynecta\LaravelSdk\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Paynecta\LaravelSdk\Services\CheckoutService checkouts()
 * @method static \Paynecta\LaravelSdk\Services\TransactionService transactions()
 * @method static \Paynecta\LaravelSdk\Services\TapFlowService tapflows()
 * @method static \Paynecta\LaravelSdk\Services\WebhookEndpointService webhooks()
 * @method static \Paynecta\LaravelSdk\Services\IntegrationService integrations()
 * @method static \Paynecta\LaravelSdk\Services\BankService banks()
 * @method static \Paynecta\LaravelSdk\Services\BalanceService balance()
 * @method static array whoami()
 * @method static string accessToken(bool $fresh = false)
 * @method static void forgetToken()
 * @method static array get(string $path, array $query = [])
 * @method static array post(string $path, array $body = [])
 * @method static array put(string $path, array $body = [])
 * @method static array delete(string $path, array $body = [])
 *
 * @see \Paynecta\LaravelSdk\PaynectaClient
 */
class Paynecta extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Paynecta\LaravelSdk\PaynectaClient::class;
    }
}
