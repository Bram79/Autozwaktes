<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PartsCategory extends Model
{
    use FlushesSiteCache;

    protected $guarded = [];

    protected $casts = ['enabled' => 'boolean'];

    /** The category for a defect description ("Remleiding poreus" matches the keyword "rem"), or null. */
    public static function forDefect(string $description): ?array
    {
        $categories = Cache::rememberForever('parts-categories', fn () => static::where('enabled', true)->orderBy('id')->get()->map->only(['id', 'name', 'keywords'])->all());
        $description = mb_strtolower($description);

        foreach ($categories as $category) {
            foreach (explode(',', $category['keywords']) as $keyword) {
                $keyword = trim(mb_strtolower($keyword));

                if ($keyword !== '' && str_contains($description, $keyword)) {
                    return $category;
                }
            }
        }

        return null;
    }
}
