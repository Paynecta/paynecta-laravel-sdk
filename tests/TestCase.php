<?php

namespace Paynecta\LaravelSdk\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Paynecta\LaravelSdk\PaynectaServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PaynectaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('paynecta.public_key', 'pyn_pk_test_aaaaaaaaaa');
        $app['config']->set('paynecta.secret_key', 'pyn_sk_test_bbbbbbbbbb');
        $app['config']->set('paynecta.webhook_secret', 'whsec_testing_only');
        $app['config']->set('cache.default', 'array');
    }
}
