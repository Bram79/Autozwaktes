<?php

namespace App\Providers;

use App\Support\AnonymousSessionHandler;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(RequestMemo::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // same as Laravel's "database" session driver, but without the IP address and browser in the table
        Session::extend('database', fn ($app) => new AnonymousSessionHandler(
            $app['db']->connection(config('session.connection')),
            config('session.table'),
            config('session.lifetime'),
            $app,
        ));

        // live, every link and redirect must be https (the server itself may only see http behind its proxy)
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
