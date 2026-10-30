<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // live, every link and redirect must be https (the server itself may only see http behind its proxy)
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
