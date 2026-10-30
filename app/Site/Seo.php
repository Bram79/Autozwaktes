<?php

namespace App\Site;

use App\Models\CarModel;
use App\Models\Generation;

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
}
