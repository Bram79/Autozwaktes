<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleCount extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }
}
