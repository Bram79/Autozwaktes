<?php

use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\NoSessionForBots;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // before the session starts: a bot that only reads gets no row in the sessions table
        $middleware->web(prepend: [NoSessionForBots::class], append: [SecurityHeaders::class, ContentSecurityPolicy::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // a failed validation flashes the input back to the form: a license plate must never end up in the session
        $exceptions->dontFlash(['kenteken']);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
