<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveWeeklyReviewRequest;
use App\Services\BudgetReportService;
use App\Services\PeriodResolver;
use App\Services\TimeAggregationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeeklyReviewController extends Controller
{
    public function __construct(
        private readonly PeriodResolver $periods,
        private readonly BudgetReportService $budgets,
        private readonly TimeAggregationService $aggregation,
    ) {
    }

    /**
     * The stored answers plus the numbers the questions are about, so the
     * review never depends on memory.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $week = $request->filled('week_start')
            ? $this->periods->weekFromDate($user, $request->string('week_start')->toString())
            : $this->periods->week($user);

        $weekStart = $week->start->format('Y-m-d');

        return response()->json([
            'week_start' => $weekStart,
            'review' => $user->weeklyReviews()->where('week_start', $weekStart)->first(),
            'context' => [
                'tracked_seconds' => $this->aggregation->trackedSeconds($user, $week),
                'coverage' => round($this->aggregation->coverage($user, $week), 4),
                'budget' => $this->budgets->forWeek($user, $week),
            ],
        ]);
    }

    public function store(SaveWeeklyReviewRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $weekStart = $this->periods->weekFromDate($user, $data['week_start'])->start->format('Y-m-d');

        $review = $user->weeklyReviews()->updateOrCreate(
            ['week_start' => $weekStart],
            collect($data)->except('week_start')->all(),
        );

        return response()->json(['review' => $review]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'reviews' => $request->user()->weeklyReviews()->orderByDesc('week_start')->get(),
        ]);
    }
}
