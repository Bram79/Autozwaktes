<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarModel extends Model
{
    use \App\Models\Concerns\FlushesSiteCache;

    protected $guarded = [];

    /** Only models that have statistics: a model without numbers has no page worth visiting. */
    public function scopeWithStats(Builder $query): Builder
    {
        return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('defect_stats')->whereColumn('defect_stats.car_model_id', 'car_models.id'));
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class)->orderBy('year_from');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ModelAlias::class);
    }
}
