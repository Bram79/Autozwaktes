<?php

namespace App\Models\Concerns;

use App\Support\RequestMemo;
use Illuminate\Support\Facades\Cache;

/** Changes made in the admin must show up on the site right away, so the cache is cleared on every save or delete. */
trait FlushesSiteCache
{
    protected static function bootFlushesSiteCache(): void
    {
        static::saved(fn () => static::flushSiteCache());
        static::deleted(fn () => static::flushSiteCache());
    }

    protected static function flushSiteCache(): void
    {
        Cache::flush();
        app(RequestMemo::class)->flush();
    }
}
