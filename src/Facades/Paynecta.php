<?php

namespace Paynecta\LaravelSdk\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array verifyAuth()
 * @method static \Paynecta\LaravelSdk\PaynectaClient setTimeout(int $seconds)
 * @method static \Paynecta\LaravelSdk\PaynectaClient setBaseUrl(string $url)
 * 
 * @see \Paynecta\LaravelSdk\PaynectaClient
 */
class Paynecta extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'paynecta';
    }
}