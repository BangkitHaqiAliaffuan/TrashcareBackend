<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS on Railway (Railway proxies terminate SSL and forward via HTTP internally)
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
