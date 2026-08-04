<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyReview extends Model
{
    /** @use HasFactory<\Database\Factories\WeeklyReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'week_start',
        'biggest_time_leak',
        'most_neglected_priority',
        'what_worked',
        'what_did_not_work',
        'next_week_adjustment',
        'notes',
    ];

    /**
     * Stored and queried as a plain `Y-m-d` string so a week key means the
     * same thing on MySQL DATE columns and on SQLite text columns.
     */
    protected function weekStart(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : CarbonImmutable::parse($value)->format('Y-m-d'),
            set: fn ($value) => CarbonImmutable::parse($value)->format('Y-m-d'),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
