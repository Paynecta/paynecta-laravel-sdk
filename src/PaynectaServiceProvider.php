<?php

namespace Paynecta\LaravelSdk;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Paynecta\LaravelSdk\Services\WebhookService;

class PaynectaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge package config with app config
        $this->mergeConfigFrom(
            __DIR__.'/config/paynecta.php', 'paynecta'
        );

        // Register the main Paynecta client as a singleton
        $this->app->singleton('paynecta', function ($app) {
            return new PaynectaClient(
                config('paynecta.api_key'),
                config('paynecta.email')
            );
        });

        // Create an alias for dependency injection
        $this->app->alias('paynecta', PaynectaClient::class);

        // Register webhook service
        $this->app->singleton(WebhookService::class, function ($app) {
            return new WebhookService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config file when running in console
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/paynecta.php' => config_path('paynecta.php'),
            ], 'paynecta-config');
        }

        // Register webhook routes
        $this->registerRoutes();
    }

    /**
     * Register webhook routes
     */
    protected function registerRoutes(): void
    {
        Route::group([
            'middleware' => config('paynecta.webhook_middleware', ['api']),
        ], function () {
            Route::post(
                config('paynecta.webhook_path', 'paynecta/webhook'),
                [\Paynecta\LaravelSdk\Http\Controllers\WebhookController::class, 'handle']
            )->name('paynecta.webhook');
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['paynecta', PaynectaClient::class, WebhookService::class];
    }
}