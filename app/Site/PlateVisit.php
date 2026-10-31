<?php

namespace App\Site;

/**
 * Remembers for a short while that a visitor came here through the license plate search, so that the vehicle report
 * and the new price of that car stay visible when the page is reloaded or another year of the same model is opened.
 * It stores a time, the model (a number), the build year and the new price of that car, never the plate.
 * On the page of another model none of this is shown: it would be about a different car.
 */
class PlateVisit
{
    private const KEY = 'from_plate_until';

    private const MODEL_KEY = 'from_plate_model';

    private const YEAR_KEY = 'from_plate_year';

    private const PRICE_KEY = 'from_plate_price';

    private const MINUTES = 30;

    /** $price: the catalogue price of the searched car in euros, if the RDW has one. */
    public static function mark(?int $modelId = null, ?int $year = null, ?int $price = null): void
    {
        session()->put(self::KEY, now()->addMinutes(self::MINUTES)->timestamp);
        session()->put(self::MODEL_KEY, $modelId);
        session()->put(self::YEAR_KEY, $year);
        session()->put(self::PRICE_KEY, $price);
    }

    public static function active(): bool
    {
        return (int) session(self::KEY, 0) > now()->timestamp;
    }

    /** Did the visitor search a car of this model (and is that still recent)? */
    public static function forModel(int $modelId): bool
    {
        return self::active() && (int) session(self::MODEL_KEY, 0) === $modelId;
    }

    /** The build year of the searched car, for the text of the report. */
    public static function year(): ?int
    {
        return self::active() ? (session(self::YEAR_KEY) ?: null) : null;
    }

    /** The new price of the car the visitor searched for, for as long as the visit lasts. */
    public static function price(): ?int
    {
        return self::active() ? session(self::PRICE_KEY) : null;
    }
}
