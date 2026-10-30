<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** A suggested generation for a model. It only reaches the site once it is approved and applied. */
class GenerationProposal extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $guarded = [];

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    /**
     * Replace the generations of a model by its approved proposals.
     * Not done while proposals are still pending, or when a generation already has a buying advice (that text would be lost).
     *
     * @return string|null the reason why nothing was changed, or null when it worked
     */
    public static function applyFor(CarModel $model): ?string
    {
        $proposals = static::where('car_model_id', $model->id)->whereIn('status', [self::PENDING, self::APPROVED])->orderBy('year_from')->get();

        if ($proposals->where('status', self::PENDING)->isNotEmpty()) {
            return 'Er staan nog voorstellen open voor dit model.';
        }
        if ($proposals->isEmpty()) {
            return 'Geen goedgekeurde voorstellen.';
        }
        if ($model->generations()->whereNotNull('buying_advice')->where('buying_advice', '!=', '')->exists()) {
            return 'Dit model heeft al een koopadvies; pas de generaties handmatig aan.';
        }

        DB::transaction(function () use ($model, $proposals) {
            $model->generations()->delete();

            foreach ($proposals as $proposal) {
                $slug = Str::slug($proposal->code);
                if ($model->generations()->where('slug', $slug)->exists()) {
                    $slug .= '-'.$proposal->year_from;
                }

                $model->generations()->create([
                    'code' => $proposal->code,
                    'slug' => $slug,
                    'year_from' => $proposal->year_from,
                    'year_to' => $proposal->year_to,
                ]);
            }

            // done: these proposals do not need to be looked at again
            static::where('car_model_id', $model->id)->delete();
        });

        return null;
    }
}
