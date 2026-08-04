<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    /** @use HasFactory<\Database\Factories\TimeEntryFactory> */
    use HasFactory;

    public const SOURCES = ['web', 'mobile', 'rainmeter', 'manual', 'system'];

    protected $fillable = [
        'user_id',
        'category_id',
        'description',
        'started_at',
        'ended_at',
        'duration_seconds',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Entries that overlap the half-open interval [$from, $to). Open entries are
     * treated as running forever, so they always overlap a range ending later
     * than their start.
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        // Timestamps are stored in UTC and the query builder binds datetimes
        // verbatim, so local-timezone bounds must be normalised here or the
        // wall clock would be compared against UTC columns.
        $from = CarbonImmutable::instance($from)->setTimezone('UTC');
        $to = CarbonImmutable::instance($to)->setTimezone('UTC');

        return $query->where('started_at', '<', $to)
            ->where(function (Builder $inner) use ($from) {
                $inner->whereNull('ended_at')->orWhere('ended_at', '>', $from);
            });
    }
}
