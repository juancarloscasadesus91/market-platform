<?php

namespace App\Providers;

use App\Services\SchwabAuthService;
use App\Services\SchwabOptionChainService;
use App\Services\SchwabQuoteService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Schwab services with configuration
        $this->app->singleton(SchwabAuthService::class, function ($app) {
            return new SchwabAuthService(
                appKey: config('services.schwab.app_key', ''),
                appSecret: config('services.schwab.app_secret', ''),
                callbackUrl: config('services.schwab.callback_url', ''),
                baseUrl: config('services.schwab.base_url', 'https://api.schwabapi.com'),
            );
        });

        $this->app->singleton(SchwabQuoteService::class, function ($app) {
            return new SchwabQuoteService(
                authService: $app->make(SchwabAuthService::class),
                baseUrl: config('services.schwab.base_url', 'https://api.schwabapi.com'),
            );
        });

        $this->app->singleton(SchwabOptionChainService::class, function ($app) {
            return new SchwabOptionChainService(
                authService: $app->make(SchwabAuthService::class),
                baseUrl: config('services.schwab.base_url', 'https://api.schwabapi.com'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force enable debugbar in development
        if (config('app.debug') && class_exists(\Barryvdh\Debugbar\Facade::class)) {
            try {
                \Debugbar::enable();
            } catch (\Exception $e) {
                // Silently fail if debugbar is not available
            }
        }
    }
}
