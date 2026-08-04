<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use App\Models\WeeklyBudget;
use App\Support\Period;
use Carbon\CarbonImmutable;

/**
 * Compares budgeted minutes against reality for one week. `minimum` budgets are
 * goals worth reaching, `maximum` budgets are ceilings worth respecting and
 * `reference` budgets are informational only — rest is never scored as failure.
 */
class BudgetReportService
{
    public function __construct(
        private readonly TimeAggregationService $aggregation,
        private readonly PeriodResolver $periods,
    ) {
    }

    /**
     * @return array{
     *     week_start: string,
     *     rows: list<array<string, mixed>>,
     *     most_neglected: array<string, mixed>|null,
     *     biggest_overrun: array<string, mixed>|null,
     * }
     */
    public function forWeek(User $user, Period $week, ?CarbonImmutable $now = null): array
    {
        $now ??= $this->periods->now();
        $weekStart = $week->start->format('Y-m-d');

        $budgets = $user->weeklyBudgets()
            ->where('week_start', $weekStart)
            ->get()
            ->keyBy('category_id');

        $actuals = $this->aggregation->secondsByCategory($user, $week, $now);

        $categories = $user->categories()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($categories as $category) {
            /** @var WeeklyBudget|null $budget */
            $budget = $budgets->get($category->id);
            $actualMinutes = (int) round(($actuals[$category->id] ?? 0) / 60);
            $targetMinutes = $budget?->target_minutes ?? 0;
            $type = $budget?->budget_type ?? 'reference';

            if ($budget === null && $actualMinutes === 0 && ! $category->is_active) {
                continue;
            }

            $rows[] = [
                'category_id' => $category->id,
                'category' => $category->name,
                'group_name' => $category->group_name,
                'color' => $category->color,
                'icon' => $category->icon,
                'budget_type' => $type,
                'target_minutes' => $targetMinutes,
                'actual_minutes' => $actualMinutes,
                'difference_minutes' => $actualMinutes - $targetMinutes,
                'percent' => $targetMinutes > 0
                    ? round($actualMinutes / $targetMinutes * 100, 1)
                    : null,
                'status' => $this->status($type, $targetMinutes, $actualMinutes),
            ];
        }

        return [
            'week_start' => $weekStart,
            'rows' => $rows,
            'most_neglected' => $this->mostNeglected($rows),
            'biggest_overrun' => $this->biggestOverrun($rows),
        ];
    }

    /**
     * `on_track` means nothing needs attention; `pending` means a minimum is
     * still short; `exceeded` means a maximum was passed.
     */
    public function status(string $type, int $targetMinutes, int $actualMinutes): string
    {
        if ($targetMinutes <= 0) {
            return 'reference';
        }

        return match ($type) {
            'minimum' => $actualMinutes >= $targetMinutes ? 'on_track' : 'pending',
            'maximum' => $actualMinutes > $targetMinutes ? 'exceeded' : 'on_track',
            default => 'reference',
        };
    }

    /**
     * Minimum goal with the largest shortfall.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function mostNeglected(array $rows): ?array
    {
        $candidates = array_filter(
            $rows,
            fn (array $row) => $row['budget_type'] === 'minimum'
                && $row['target_minutes'] > 0
                && $row['actual_minutes'] < $row['target_minutes'],
        );

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $a['difference_minutes'] <=> $b['difference_minutes']);

        return $candidates[0];
    }

    /**
     * Maximum limit exceeded by the largest margin.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function biggestOverrun(array $rows): ?array
    {
        $candidates = array_filter(
            $rows,
            fn (array $row) => $row['budget_type'] === 'maximum'
                && $row['target_minutes'] > 0
                && $row['actual_minutes'] > $row['target_minutes'],
        );

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['difference_minutes'] <=> $a['difference_minutes']);

        return $candidates[0];
    }

    /**
     * Weekly budget of a given type for one category, or null when unset.
     */
    public function budgetFor(User $user, ?Category $category, Period $week, string $type): ?WeeklyBudget
    {
        if ($category === null) {
            return null;
        }

        return $user->weeklyBudgets()
            ->where('category_id', $category->id)
            ->where('week_start', $week->start->format('Y-m-d'))
            ->where('budget_type', $type)
            ->first();
    }
}
