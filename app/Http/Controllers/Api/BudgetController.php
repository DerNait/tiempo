<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveBudgetsRequest;
use App\Services\BudgetReportService;
use App\Services\PeriodResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetReportService $budgets,
        private readonly PeriodResolver $periods,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $week = $request->filled('week_start')
            ? $this->periods->weekFromDate($user, $request->string('week_start')->toString())
            : $this->periods->week($user);

        return response()->json([
            'week_start' => $week->start->format('Y-m-d'),
            'budget' => $this->budgets->forWeek($user, $week),
            'templates' => $user->budgetTemplates()->get(['category_id', 'budget_type', 'target_minutes']),
        ]);
    }

    public function store(SaveBudgetsRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $week = $this->periods->weekFromDate($user, $data['week_start']);
        $weekStart = $week->start->format('Y-m-d');

        DB::transaction(function () use ($user, $data, $weekStart) {
            foreach ($data['budgets'] as $row) {
                if ((int) $row['target_minutes'] === 0 && $row['budget_type'] === 'reference') {
                    $user->weeklyBudgets()
                        ->where('category_id', $row['category_id'])
                        ->where('week_start', $weekStart)
                        ->delete();

                    continue;
                }

                $user->weeklyBudgets()->updateOrCreate(
                    ['category_id' => $row['category_id'], 'week_start' => $weekStart],
                    ['budget_type' => $row['budget_type'], 'target_minutes' => $row['target_minutes']],
                );
            }

            if ($data['save_as_template'] ?? false) {
                foreach ($data['budgets'] as $row) {
                    $user->budgetTemplates()->updateOrCreate(
                        ['category_id' => $row['category_id']],
                        ['budget_type' => $row['budget_type'], 'target_minutes' => $row['target_minutes']],
                    );
                }
            }
        });

        return response()->json([
            'week_start' => $weekStart,
            'budget' => $this->budgets->forWeek($user, $week),
        ]);
    }

    /**
     * Copy the previous week's plan into the target week, leaving weeks that
     * already have entries untouched unless `overwrite` is requested.
     */
    public function copyPrevious(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
            'overwrite' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $week = $this->periods->weekFromDate($user, $validated['week_start']);
        $target = $week->start->format('Y-m-d');
        $source = $week->start->subWeek()->format('Y-m-d');

        $sourceBudgets = $user->weeklyBudgets()->where('week_start', $source)->get();

        DB::transaction(function () use ($user, $sourceBudgets, $target, $validated) {
            foreach ($sourceBudgets as $budget) {
                $query = $user->weeklyBudgets()
                    ->where('category_id', $budget->category_id)
                    ->where('week_start', $target);

                if (! ($validated['overwrite'] ?? false) && $query->exists()) {
                    continue;
                }

                $user->weeklyBudgets()->updateOrCreate(
                    ['category_id' => $budget->category_id, 'week_start' => $target],
                    ['budget_type' => $budget->budget_type, 'target_minutes' => $budget->target_minutes],
                );
            }
        });

        return response()->json([
            'copied' => $sourceBudgets->count(),
            'week_start' => $target,
            'budget' => $this->budgets->forWeek($user, $week),
        ]);
    }

    /**
     * Apply the saved recurring template to a week.
     */
    public function applyTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
        ]);

        $user = $request->user();
        $week = $this->periods->weekFromDate($user, $validated['week_start']);
        $target = $week->start->format('Y-m-d');

        DB::transaction(function () use ($user, $target) {
            foreach ($user->budgetTemplates()->get() as $template) {
                $user->weeklyBudgets()->updateOrCreate(
                    ['category_id' => $template->category_id, 'week_start' => $target],
                    ['budget_type' => $template->budget_type, 'target_minutes' => $template->target_minutes],
                );
            }
        });

        return response()->json([
            'week_start' => $target,
            'budget' => $this->budgets->forWeek($user, $week),
        ]);
    }
}
