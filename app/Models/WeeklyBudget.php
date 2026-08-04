<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyBudget extends Model
{
    /** @use HasFactory<\Database\Factories\WeeklyBudgetFactory> */
    use HasFactory;

    public const TYPES = ['minimum', 'maximum', 'reference'];

    protected $fillable = [
        'user_id',
        'category_id',
        'week_start',
        'budget_type',
        'target_minutes',
    ];

    protected function casts(): array
    {
        return [
            'target_minutes' => 'integer',
        ];
    }

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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
