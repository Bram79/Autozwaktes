<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefectStat extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    public function defectCode(): BelongsTo
    {
        return $this->belongsTo(DefectCode::class, 'defect_code', 'code');
    }
}
