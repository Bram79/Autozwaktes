<?php

namespace App\Site;

use App\Models\Setting;

/**
 * Every outgoing affiliate link goes through /go/{partner}, so each click can be counted.
 * The real address is only known on the server and comes from the admin settings.
 */
class AffiliateLink
{
    /** The link to put on the page. */
    public static function url(string $partner, string $position, string $brand = '', string $model = '', string|int $year = '', ?int $category = null): string
    {
        return route('go', array_filter([
            'partner' => $partner,
            'plek' => $position,
            'p' => '/'.ltrim(parse_url(request()->getRequestUri(), PHP_URL_PATH) ?: '/', '/'),
            'merk' => $brand,
            'model' => $model,
            'jaar' => $year,
            'cat' => $category,
        ], fn ($v) => $v !== '' && $v !== null));
    }

    /** The real address of the partner with {merk}, {model} and {jaar} filled in, or null when the partner is not set up. */
    public static function target(string $partner, string $brand = '', string $model = '', string $year = '', ?string $categoryUrl = null): ?string
    {
        $url = $categoryUrl;

        if ($url === null) {
            $config = Setting::affiliate($partner);
            $url = ($config['enabled'] ?? false) ? ($config['url'] ?? null) : null;
        }

        if (blank($url)) {
            return null;
        }

        return str_replace(['{merk}', '{model}', '{jaar}'], [rawurlencode($brand), rawurlencode($model), rawurlencode($year)], $url);
    }
}
