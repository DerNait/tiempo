<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'week_starts_on',
        'audit_mode_enabled',
        'audit_started_at',
        'audit_days',
        'onboarded_at',
        'accent_color',
        'rainmeter_priority_category_id',
        'rainmeter_leak_category_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'audit_mode_enabled' => 'boolean',
            'audit_started_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'week_starts_on' => 'integer',
            'audit_days' => 'integer',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function weeklyBudgets(): HasMany
    {
        return $this->hasMany(WeeklyBudget::class);
    }

    public function budgetTemplates(): HasMany
    {
        return $this->hasMany(BudgetTemplate::class);
    }

    public function weeklyReviews(): HasMany
    {
        return $this->hasMany(WeeklyReview::class);
    }

    public function rainmeterPriorityCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'rainmeter_priority_category_id');
    }

    public function rainmeterLeakCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'rainmeter_leak_category_id');
    }

    /**
     * Timezone used for every local-day and local-week boundary.
     */
    public function effectiveTimezone(): string
    {
        return $this->timezone ?: config('app.user_timezone', 'America/Guatemala');
    }

    public function openTimeEntry(): ?TimeEntry
    {
        return $this->timeEntries()->whereNull('ended_at')->latest('started_at')->first();
    }
}
