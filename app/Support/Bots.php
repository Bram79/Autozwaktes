<?php

namespace App\Support;

/** Who is not a visitor: search engines, link previews, scripts and monitors, and requests without a user agent. */
class Bots
{
    private const PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|curl|wget|python|monitor|uptime|java|go-http|httpclient/i';

    public static function isBot(?string $userAgent): bool
    {
        return blank($userAgent) || preg_match(self::PATTERN, $userAgent) === 1;
    }
}
