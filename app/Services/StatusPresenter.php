<?php

namespace App\Services;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\TimeEntryResource;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The payload the app polls after every tracking action: what is running now
 * plus the day and week headline numbers.
 */
class StatusPresenter
{
    public function __construct(
        private readonly TimeAggregationService $aggregation,
        private readonly PeriodResolver $periods,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, ?CarbonImmutable $now = null): array
    {
        $now ??= $this->periods->now();
        $today = $this->periods->day($user, $now);
        $week = $this->periods->week($user, $now);

        $open = $user->timeEntries()->with('category')->whereNull('ended_at')->latest('started_at')->first();

        return [
            'server_time' => $now->toIso8601String(),
            'server_time_unix' => $now->getTimestamp(),
            'timezone' => $user->effectiveTimezone(),
            'current_entry' => $open !== null ? (new TimeEntryResource($open))->resolve() : null,
            'today' => [
                'date' => $today->start->format('Y-m-d'),
                'tracked_seconds' => $this->aggregation->trackedSeconds($user, $today, $now),
                'elapsed_seconds' => $this->aggregation->elapsedSeconds($today, $now),
                'coverage' => round($this->aggregation->coverage($user, $today, $now), 4),
            ],
            'week' => [
                'week_start' => $week->start->format('Y-m-d'),
                'tracked_seconds' => $this->aggregation->trackedSeconds($user, $week, $now),
                'elapsed_seconds' => $this->aggregation->elapsedSeconds($week, $now),
                'coverage' => round($this->aggregation->coverage($user, $week, $now), 4),
            ],
            'audit' => $this->audit($user, $now),
            'favorites' => CategoryResource::collection(
                $user->categories()->where('is_active', true)->where('is_favorite', true)
                    ->orderBy('sort_order')->get()
            )->resolve(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function audit(User $user, CarbonImmutable $now): ?array
    {
        if (! $user->audit_mode_enabled || $user->audit_started_at === null) {
            return null;
        }

        $start = CarbonImmutable::instance($user->audit_started_at);
        $days = max(1, $user->audit_days ?: 7);
        $end = $start->addDays($days);
        $elapsedDays = min($days, max(0, (int) floor($start->diffInDays($now)) + 1));

        return [
            'started_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
            'total_days' => $days,
            'day_number' => $elapsedDays,
            'finished' => $now->greaterThanOrEqualTo($end),
        ];
    }
}
