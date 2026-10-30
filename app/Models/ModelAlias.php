<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelAlias extends Model
{
    use FlushesSiteCache;

    protected $guarded = [];

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }
}
