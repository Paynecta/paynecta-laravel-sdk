<?php

namespace Paynecta\LaravelSdk;

use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['paynecta', PaynectaClient::class];
    }
}