<?php

namespace App\Services;

use App\Models\Category;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Period;
use Carbon\CarbonImmutable;

/**
 * Builds the fixed plain-text payload the Rainmeter skin parses. The line
 * order, names and formatting are a contract: changing them breaks the skin,
 * so `tests/Feature/RainmeterContractTest.php` pins the whole body.
 */
class RainmeterStatusService
{
    /**
     * Keys in the exact order the skin expects them.
     */
    public const KEYS = [
        'ok',
        'server_time_unix',
        'current_activity_active',
        'current_activity_name',
        'current_activity_started_at',
        'current_activity_elapsed_seconds',
        'today_tracked_minutes',
        'today_elapsed_minutes',
        'week_tracked_minutes',
        'week_elapsed_minutes',
        'priority_name',
        'priority_actual_minutes',
        'priority_budget_minutes',
        'leak_name',
        'leak_actual_minutes',
        'leak_limit_minutes',
    ];

    public function __construct(
        private readonly TimeAggregationService $aggregation,
        private readonly PeriodResolver $periods,
    ) {
    }

    public function render(User $user, ?CarbonImmutable $now = null): string
    {
        $values = $this->values($user, $now);
        $lines = [];

        foreach (self::KEYS as $key) {
            $lines[] = $key.'='.$values[$key];
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<string, string>
     */
    public function values(User $user, ?CarbonImmutable $now = null): array
    {
        $now ??= $this->periods->now();

        $today = $this->periods->day($user, $now);
        $week = $this->periods->week($user, $now);

        $open = $user->timeEntries()->with('category')->whereNull('ended_at')->latest('started_at')->first();

        [$priorityName, $priorityActual, $priorityBudget] = $this->categoryStats(
            $user,
            $user->rainmeterPriorityCategory,
            $week,
            'minimum',
            $now,
        );

        [$leakName, $leakActual, $leakLimit] = $this->categoryStats(
            $user,
            $user->rainmeterLeakCategory,
            $week,
            'maximum',
            $now,
        );

        return [
            'ok' => '1',
            'server_time_unix' => (string) $now->getTimestamp(),
            'current_activity_active' => $open !== null ? '1' : '0',
            'current_activity_name' => $open !== null ? $this->sanitize($open->category?->name ?? '') : '',
            'current_activity_started_at' => $open !== null
                ? CarbonImmutable::instance($open->started_at)
                    ->setTimezone($user->effectiveTimezone())
                    ->format('H:i')
                : '',
            'current_activity_elapsed_seconds' => (string) $this->elapsedSeconds($open, $now),
            'today_tracked_minutes' => (string) $this->minutes($this->aggregation->trackedSeconds($user, $today, $now)),
            'today_elapsed_minutes' => (string) $this->minutes($this->aggregation->elapsedSeconds($today, $now)),
            'week_tracked_minutes' => (string) $this->minutes($this->aggregation->trackedSeconds($user, $week, $now)),
            'week_elapsed_minutes' => (string) $this->minutes($this->aggregation->elapsedSeconds($week, $now)),
            'priority_name' => $priorityName,
            'priority_actual_minutes' => (string) $priorityActual,
            'priority_budget_minutes' => (string) $priorityBudget,
            'leak_name' => $leakName,
            'leak_actual_minutes' => (string) $leakActual,
            'leak_limit_minutes' => (string) $leakLimit,
        ];
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function categoryStats(
        User $user,
        ?Category $category,
        Period $week,
        string $budgetType,
        CarbonImmutable $now,
    ): array {
        if ($category === null) {
            return ['', 0, 0];
        }

        $seconds = $this->aggregation->secondsByCategory($user, $week, $now)[$category->id] ?? 0;

        $budget = $user->weeklyBudgets()
            ->where('category_id', $category->id)
            ->where('week_start', $week->start->format('Y-m-d'))
            ->where('budget_type', $budgetType)
            ->first();

        return [
            $this->sanitize($category->name),
            $this->minutes($seconds),
            (int) ($budget?->target_minutes ?? 0),
        ];
    }

    private function elapsedSeconds(?TimeEntry $open, CarbonImmutable $now): int
    {
        if ($open === null) {
            return 0;
        }

        return max(0, $now->getTimestamp() - $open->started_at->getTimestamp());
    }

    private function minutes(int $seconds): int
    {
        return intdiv(max(0, $seconds), 60);
    }

    /**
     * Names must never break the one-key-per-line format.
     */
    private function sanitize(string $value): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
    }
}
