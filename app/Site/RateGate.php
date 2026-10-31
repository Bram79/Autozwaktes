<?php

namespace App\Site;

use Illuminate\Support\Facades\RateLimiter;

/** "At most so many times in so many seconds", for the plate search, the search box, the APK form and so on. */
class RateGate
{
    /** True and counted when there is room left under the limit; false (and not counted) when the limit is reached. */
    public static function attempt(string $key, int $max, int $seconds = 60): bool
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return false;
        }

        RateLimiter::hit($key, $seconds);

        return true;
    }
}
