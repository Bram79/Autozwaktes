<?php

namespace App\Http\Controllers;

use App\Models\AffiliateClick;
use App\Models\PartsCategory;
use App\Site\AffiliateLink;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    /** Count the click and send the visitor on to the partner. Nothing personal is saved. */
    public function go(Request $request, string $partner)
    {
        abort_unless(array_key_exists($partner, config('site.affiliates')), 404);

        $brand = (string) $request->query('merk', '');
        $model = (string) $request->query('model', '');
        $year = (string) $request->query('jaar', '');

        $category = null;
        if ($partner === 'parts' && $request->query('cat')) {
            $category = PartsCategory::where('enabled', true)->find($request->query('cat'));
        }

        $target = AffiliateLink::target($partner, $brand, $model, $year, $category?->url);
        abort_if($target === null, 404);

        // search engines and other bots follow links too: they are not customers
        if (! preg_match('/bot|crawl|spider|slurp|preview/i', (string) $request->userAgent())) {
            $page = (string) $request->query('p', '/');

            AffiliateClick::create([
                'partner' => $partner,
                'page' => str_starts_with($page, '/') ? mb_substr($page, 0, 190) : '/',
                'position' => mb_substr(($category ? 'gebrek: '.$category->name : (string) $request->query('plek', 'onbekend')), 0, 60),
            ]);
        }

        return redirect()->away($target)->withHeaders(['X-Robots-Tag' => 'noindex, nofollow', 'Referrer-Policy' => 'no-referrer']);
    }
}
