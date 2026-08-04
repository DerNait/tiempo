<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryResource;
use App\Models\User;
use App\Services\BudgetReportService;
use App\Services\PeriodResolver;
use App\Services\TimeAggregationService;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly TimeAggregationService $aggregation,
        private readonly BudgetReportService $budgets,
        private readonly PeriodResolver $periods,
    ) {
    }

    public function day(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = $this->periods->now();

        $day = $request->filled('date')
            ? $this->periods->dayFromDate($user, $request->string('date')->toString())
            : $this->periods->day($user, $now);

        return response()->json([
            'date' => $day->start->format('Y-m-d'),
            'tracked_seconds' => $this->aggregation->trackedSeconds($user, $day, $now),
            'elapsed_seconds' => $this->aggregation->elapsedSeconds($day, $now),
            'coverage' => round($this->aggregation->coverage($user, $day, $now), 4),
            'by_category' => $this->byCategory($user, $day, $now),
            'timeline' => TimeEntryResource::collection(
                $this->aggregation->entriesIn($user, $day)
            )->resolve(),
            'gaps' => $this->aggregation->gaps($user, $day, $now),
        ]);
    }

    public function week(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = $this->periods->now();

        $week = $request->filled('week_start')
            ? $this->periods->weekFromDate($user, $request->string('week_start')->toString())
            : $this->periods->week($user, $now);

        $previous = new Period($week->start->subWeek(), $week->start);

        return response()->json([
            'week_start' => $week->start->format('Y-m-d'),
            'week_end' => $week->end->subDay()->format('Y-m-d'),
            'tracked_seconds' => $this->aggregation->trackedSeconds($user, $week, $now),
            'elapsed_seconds' => $this->aggregation->elapsedSeconds($week, $now),
            'coverage' => round($this->aggregation->coverage($user, $week, $now), 4),
            'by_category' => $this->byCategory($user, $week, $now),
            'daily' => $this->aggregation->dailyBreakdown($user, $week, $now),
            'budget' => $this->budgets->forWeek($user, $week, $now),
            'previous_week' => [
                'week_start' => $previous->start->format('Y-m-d'),
                'tracked_seconds' => $this->aggregation->trackedSeconds($user, $previous, $now),
                'by_category' => $this->byCategory($user, $previous, $now),
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byCategory(User $user, Period $period, CarbonImmutable $now): array
    {
        $totals = $this->aggregation->secondsByCategory($user, $period, $now);

        if ($totals === []) {
            return [];
        }

        $categories = $user->categories()->whereIn('id', array_keys($totals))->get()->keyBy('id');
        $sum = array_sum($totals);

        $rows = [];

        foreach ($totals as $categoryId => $seconds) {
            $category = $categories->get($categoryId);

            $rows[] = [
                'category_id' => $categoryId,
                'name' => $category?->name ?? 'Desconocida',
                'group_name' => $category?->group_name,
                'color' => $category?->color ?? '#8b8b9e',
                'icon' => $category?->icon ?? '⏱️',
                'seconds' => $seconds,
                'minutes' => intdiv($seconds, 60),
                'share' => $sum > 0 ? round($seconds / $sum, 4) : 0,
            ];
        }

        return $rows;
    }
}
