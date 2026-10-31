<?php

namespace App\Site;

/**
 * Remembers for a short while that a visitor came here through the license plate search, so that the vehicle report
 * stays prominent when the page is reloaded or another year is opened. It stores a time and the new price of that car, never the plate.
 */
class PlateVisit
{
    private const KEY = 'from_plate_until';

    private const MINUTES = 30;

    private const PRICE_KEY = 'from_plate_price';

    /** $price: the catalogue price of the searched car in euros, if the RDW has one. Only the amount is kept, not the plate. */
    public static function mark(?int $price = null): void
    {
        session()->put(self::KEY, now()->addMinutes(self::MINUTES)->timestamp);
        session()->put(self::PRICE_KEY, $price);
    }

    /** The new price of the car the visitor searched for, for as long as the visit lasts. */
    public static function price(): ?int
    {
        return self::active() ? session(self::PRICE_KEY) : null;
    }

    public static function active(): bool
    {
        return (int) session(self::KEY, 0) > now()->timestamp;
    }
}
