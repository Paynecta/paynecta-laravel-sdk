<?php

namespace Paynecta\LaravelSdk;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Paynecta\LaravelSdk\Console\RegisterWebhookCommand;
use Paynecta\LaravelSdk\Http\Controllers\WebhookController;
use Paynecta\LaravelSdk\Services\WebhookService;

class PaynectaServiceProvider extends ServiceProvider
{
    /**
     * Reported when this application registers itself, so somebody looking
     * at a shop that is behaving oddly can see which version it runs.
     */
    public const VERSION = '2.0.0';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/paynecta.php', 'paynecta');

        // Singletons. The client caches an access token in memory as well as
        // in the cache store, and a new instance per resolve would throw
        // that away on every call.
        $this->app->singleton(PaynectaClient::class, fn () => new PaynectaClient());
        $this->app->singleton(WebhookService::class, fn () => new WebhookService());
        $this->app->alias(PaynectaClient::class, 'paynecta');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/paynecta.php' => config_path('paynecta.php'),
        ], 'paynecta-config');

        $this->registerWebhookRoute();

        if ($this->app->runningInConsole()) {
            $this->commands([RegisterWebhookCommand::class]);
        }
    }

    /**
     * The route deliveries arrive on.
     *
     * No CSRF and no 'web' group: a webhook is a server posting to you, and
     * it carries no session and no token. What stands in for that is the
     * signature, checked before the body is read.
     *
     * Not registered when there is no path configured, so an application
     * that handles deliveries its own way is not also given ours.
     */
    protected function registerWebhookRoute(): void
    {
        $path = config('paynecta.webhook_path');
        if (! is_string($path) || trim($path) === '') {
            return;
        }

        Route::group([
            'middleware' => config('paynecta.webhook_middleware', ['api']),
        ], function () use ($path) {
            Route::post($path, WebhookController::class)->name('paynecta.webhook');
        });
    }
}
