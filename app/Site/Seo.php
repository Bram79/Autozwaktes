<?php

namespace App\Site;

use App\Models\CarModel;
use App\Models\Generation;
use Illuminate\Support\Str;

/** Builds titles and descriptions. Kept in one place so they stay consistent. */
class Seo
{
    public static function generationName(Generation $g): string
    {
        return "{$g->carModel->brand->name} {$g->carModel->name} {$g->code}";
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

    /** A title that fits in the search results: too long becomes "<name>: APK-gebreken", or just the name, or cut at a word. */
    public static function title(string $title): string
    {
        if (mb_strlen($title) <= self::TITLE_MAX) {
            return $title;
        }

        $name = Str::before($title, ': ');
        foreach ([$name.': APK-gebreken', $name] as $candidate) {
            if (mb_strlen($candidate) <= self::TITLE_MAX) {
                return $candidate;
            }
        }

        return Str::limit($name, self::TITLE_MAX - 1, '…', preserveWords: true);
    }

    /** A description that is not cut off by the search engine: at most 160 characters, cut at a word. */
    public static function description(string $description): string
    {
        return mb_strlen($description) <= self::DESCRIPTION_MAX
            ? $description
            : Str::limit($description, self::DESCRIPTION_MAX - 1, '…', preserveWords: true);
    }
}
