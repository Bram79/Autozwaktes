<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApkReminder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'apk_expires_at' => 'date',
        'confirmed_at' => 'datetime',
        'last_reminded_for' => 'date',
    ];

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
