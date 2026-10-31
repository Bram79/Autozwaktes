<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content Security Policy for the public site: the browser only runs scripts that have this request's nonce.
 * Set CSP_MODE to "report-only" to try it without blocking anything, or "off" to switch it off.
 *
 * - The admin panel (Filament) is left out: it needs inline scripts and styles.
 * - While advertisements are on, scripts still need the nonce (or must be loaded by a script that has it); images, frames and connections may go to any https address.
 * - Styles may be inline: the site uses style="..." for bar widths and animation delays.
 */
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = (string) config('site.csp');

        if ($mode === 'off' || $request->is('admin', 'admin/*')) {
            return $next($request);
        }

        // Vite and Livewire read this nonce when they print their <script> tags, so it must be set before the page is rendered
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $header = $mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
        if (! $response->headers->has($header)) {
            $response->headers->set($header, $this->policy($nonce));
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $directives = [
            "default-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        // while `npm run dev` runs on your own computer, the browser also loads scripts and styles from the Vite server
        $dev = $this->viteDevServer();

        if (config('site.ads.enabled')) {
            // An ad network loads scripts, images and frames from many hosts that we cannot list. So: our own script (with the nonce)
            // may load other scripts ('strict-dynamic'), which is how the ad script gets in after the visitor said yes, but an
            // injected script without the nonce still does not run. Images, frames and connections may go to any https address.
            $directives[] = trim("script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' {$dev}");
            $directives[] = trim("style-src 'self' 'unsafe-inline' {$dev}");
            $directives[] = "img-src 'self' data: https:";
            $directives[] = 'frame-src https:';
            $directives[] = trim("connect-src 'self' https: ".str_replace('http', 'ws', $dev));
        } else {
            $directives[] = trim("script-src 'self' 'nonce-{$nonce}' {$dev}");
            $directives[] = trim("style-src 'self' 'unsafe-inline' {$dev}");
            $directives[] = trim("connect-src 'self' ".str_replace('http', 'ws', $dev).' '.$dev);
            $directives[] = "img-src 'self' data:";
        }

        return implode('; ', $directives);
    }

    /** The address of the Vite dev server (e.g. http://127.0.0.1:5173), only on a local machine while it is running. */
    private function viteDevServer(): string
    {
        $hotFile = public_path('hot');

        return app()->environment('local') && is_file($hotFile) ? trim((string) file_get_contents($hotFile)) : '';
    }
}
