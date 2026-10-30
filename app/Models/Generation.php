<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Generation extends Model
{
    use \App\Models\Concerns\FlushesSiteCache;

    protected $guarded = [];

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    public function adviceDrafts(): HasMany
    {
        return $this->hasMany(AdviceDraft::class);
    }

    public function defectStats(): HasMany
    {
        return $this->hasMany(DefectStat::class);
    }

    /** Does a car built in this year belong to this generation? */
    public function coversYear(int $year): bool
    {
        return $year >= $this->year_from && ($this->year_to === null || $year <= $this->year_to);
    }
}
