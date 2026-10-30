<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Simple key/value settings that can be changed in the admin. */
class Setting extends Model
{
    use FlushesSiteCache;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings', fn () => static::pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    public static function put(string $key, array $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** Affiliate block settings: the values from config/site.php, overridden by what is saved in the admin. */
    public static function affiliate(string $type): ?array
    {
        $default = config("site.affiliates.{$type}");

        if ($default === null) {
            return null;
        }

        return array_merge($default, array_filter(static::get("affiliate.{$type}", []), fn ($v) => $v !== null && $v !== ''));
    }
}
