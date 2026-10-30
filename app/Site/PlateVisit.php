<?php

namespace App\Site;

/**
 * Remembers for a short while that a visitor came here through the license plate search, so that the vehicle report
 * stays prominent when the page is reloaded or another year is opened. It only stores a time, never the plate or the car.
 */
class PlateVisit
{
    private const KEY = 'from_plate_until';

    private const MINUTES = 30;

    public static function mark(): void
    {
        session()->put(self::KEY, now()->addMinutes(self::MINUTES)->timestamp);
    }

    public static function active(): bool
    {
        return (int) session(self::KEY, 0) > now()->timestamp;
    }
}
