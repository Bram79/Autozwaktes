<?php

namespace App\Site;

use App\Models\CarModel;
use App\Models\Generation;
use Illuminate\Support\Str;

/** Builds titles and descriptions. Kept in one place so they stay consistent. */
class Seo
{
    /** The code of the one generation that `rdw:discover` makes for a model: it is no real generation, so it is not part of the name. */
    public const ALL_YEARS = 'Alle bouwjaren';

    public static function generationName(Generation $g): string
    {
        $name = "{$g->carModel->brand->name} {$g->carModel->name}";

        return $g->code === self::ALL_YEARS ? $name : "{$name} {$g->code}";
    }

    public static function yearsLabel(Generation $g): string
    {
        return $g->year_from.'–'.($g->year_to ?? 'heden');
    }

    public static function modelName(CarModel $m): string
    {
        return "{$m->brand->name} {$m->name}";
    }

    private const TITLE_MAX = 60;

    private const DESCRIPTION_MAX = 160;

    /** A title that fits in the search results: first without the site name, then "<name>: APK-gebreken", then just the name, then cut at a word. */
    public static function title(string $title): string
    {
        if (mb_strlen($title) <= self::TITLE_MAX) {
            return $title;
        }

        $withoutSite = Str::replaceEnd(' | '.config('site.name'), '', $title);
        $name = Str::before($withoutSite, ': ');

        // "Minste APK-gebreken bij Audi" already says it: adding ": APK-gebreken" would say it twice
        $candidates = [$withoutSite, str_contains($name, 'APK-gebreken') ? null : $name.': APK-gebreken', $name];

        foreach (array_filter($candidates) as $candidate) {
            if (mb_strlen($candidate) <= self::TITLE_MAX) {
                return $candidate;
            }
        }

        return Str::limit($name, self::TITLE_MAX - 1, '…', preserveWords: true);
    }

    /** Fuel as a noun for in a sentence: "elektrische auto's", "benzineauto's". */
    public static function fuelCars(string $fuel): string
    {
        return match (mb_strtolower($fuel)) {
            'elektriciteit' => "elektrische auto's",
            'hybride' => "hybride auto's",
            'benzine' => "benzineauto's",
            'diesel' => "dieselauto's",
            'lpg' => "LPG-auto's",
            default => mb_strtolower($fuel)."-auto's",
        };
    }

    /** A description that is not cut off by the search engine: at most 160 characters, cut at a word. */
    public static function description(string $description): string
    {
        return mb_strlen($description) <= self::DESCRIPTION_MAX
            ? $description
            : Str::limit($description, self::DESCRIPTION_MAX - 1, '…', preserveWords: true);
    }
}
