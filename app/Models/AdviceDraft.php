<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A buying advice written by the AI from our statistics. It only goes live when a person approves it. */
class AdviceDraft extends Model
{
    public const CONCEPT = 'concept';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $guarded = [];

    protected $casts = ['facts' => 'array'];

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    /** Put the text on the site as the buying advice of the generation. */
    public function approve(): void
    {
        $this->generation->update(['buying_advice' => $this->body]);
        $this->update(['status' => self::APPROVED]);
    }
}
