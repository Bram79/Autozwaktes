<?php

namespace App\Http\Middleware;

use App\Support\Bots;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A search engine that reads 100,000 pages would make 100,000 rows in the sessions table, for nothing: it never fills in a form.
 * So a bot that only reads (GET or HEAD) and has no session cookie gets a session that lives in memory for this request.
 * Must run before the session starts.
 */
class NoSessionForBots
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() && ! $request->hasCookie((string) config('session.cookie')) && Bots::isBot($request->userAgent())) {
            config(['session.driver' => 'array']);
        }

        return $next($request);
    }
}
