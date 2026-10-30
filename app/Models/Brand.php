<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function carModels(): HasMany
    {
        return $this->hasMany(CarModel::class);
    }
}
