<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use FlushesSiteCache;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Brands with at least one model that has statistics. */
    public function scopeWithStats(Builder $query): Builder
    {
        return $query->whereHas('carModels', fn ($q) => $q->withStats());
    }

    public function carModels(): HasMany
    {
        return $this->hasMany(CarModel::class);
    }
}
