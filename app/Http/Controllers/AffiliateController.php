<?php

namespace App\Http\Controllers;

use App\Models\AffiliateClick;
use App\Models\PartsCategory;
use App\Site\AffiliateLink;
use App\Support\Bots;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AffiliateController extends Controller
{
    /** Count the click and send the visitor on to the partner. Nothing personal is saved. */
    public function go(Request $request, string $partner)
    {
        abort_unless(array_key_exists($partner, config('site.affiliates')), 404);

        $brand = $this->text($request, 'merk');
        $model = $this->text($request, 'model');
        $year = $this->text($request, 'jaar');

        $category = null;
        $categoryId = $this->text($request, 'cat');
        if ($partner === 'parts' && ctype_digit($categoryId)) {
            $category = PartsCategory::where('enabled', true)->find((int) $categoryId);
        }

        $target = AffiliateLink::target($partner, $brand, $model, $year, $category?->url);
        abort_if($target === null, 404);

        if ($this->countsAsCustomer($request)) {
            $page = $this->text($request, 'p');
            $position = $this->text($request, 'plek');

            AffiliateClick::create([
                'partner' => $partner,
                // only the usual shapes: a path of our own site, and a short spot name. Nobody can fill the statistics with their own text.
                'page' => preg_match('#^/[a-z0-9/_-]{0,189}$#i', $page) ? $page : '/',
                'position' => $category ? mb_substr('gebrek: '.$category->name, 0, 60) : (preg_match('/^[a-z0-9-]{1,40}$/', $position) ? $position : 'onbekend'),
            ]);
        }

        return redirect()->away($target)->withHeaders(['X-Robots-Tag' => 'noindex, nofollow', 'Referrer-Policy' => 'no-referrer']);
    }

    /** A query value as text. `?merk[]=x` gives an array: that counts as empty instead of a crash. */
    private function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    /**
     * Only real visitors are counted: not search engines, link previews, scripts, monitors, HEAD requests or requests without
     * a user agent, and not someone who clicks more than a normal visitor does (that one is still sent on, but not counted).
     */
    private function countsAsCustomer(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        if (! $request->isMethod('GET') || Bots::isBot($agent)) {
            return false;
        }

        $key = 'go:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, (int) config('site.limits.go_per_minute'))) {
            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }
}
