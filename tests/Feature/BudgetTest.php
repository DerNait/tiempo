<?php

use App\Services\BudgetReportService;
use App\Services\PeriodResolver;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->user = personalUser();
    $this->budgets = app(BudgetReportService::class);
    $this->periods = app(PeriodResolver::class);

    CarbonImmutable::setTestNow('2026-08-04T17:00:00Z');
});

function trackMinutes($user, $category, string $from, int $minutes): void
{
    $start = CarbonImmutable::parse($from, 'America/Guatemala');
    $end = $start->addMinutes($minutes);

    $user->timeEntries()->create([
        'category_id' => $category->id,
        'started_at' => $start->setTimezone('UTC'),
        'ended_at' => $end->setTimezone('UTC'),
        'duration_seconds' => $minutes * 60,
        'source' => 'manual',
    ]);
}

it('marks a minimum goal as pending until it is reached', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $this->user->weeklyBudgets()->create([
        'category_id' => $unity->id,
        'week_start' => '2026-08-03',
        'budget_type' => 'minimum',
        'target_minutes' => 600,
    ]);

    trackMinutes($this->user, $unity, '2026-08-03 09:00', 400);

    $week = $this->periods->weekFromDate($this->user, '2026-08-03');
    $row = collect($this->budgets->forWeek($this->user, $week)['rows'])
        ->firstWhere('category_id', $unity->id);

    expect($row['status'])->toBe('pending')
        ->and($row['actual_minutes'])->toBe(400)
        ->and($row['difference_minutes'])->toBe(-200)
        ->and($row['percent'])->toBe(66.7);
});

it('marks a minimum goal as on track once it is met', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $this->user->weeklyBudgets()->create([
        'category_id' => $unity->id,
        'week_start' => '2026-08-03',
        'budget_type' => 'minimum',
        'target_minutes' => 600,
    ]);

    trackMinutes($this->user, $unity, '2026-08-03 04:00', 600);

    $week = $this->periods->weekFromDate($this->user, '2026-08-03');
    $row = collect($this->budgets->forWeek($this->user, $week)['rows'])
        ->firstWhere('category_id', $unity->id);

    expect($row['status'])->toBe('on_track')
        ->and($row['difference_minutes'])->toBe(0);
});

it('only marks a maximum as exceeded when it is passed, not when it is met', function () {
    $doom = categoryNamed($this->user, 'Doom Scrolling');

    $this->user->weeklyBudgets()->create([
        'category_id' => $doom->id,
        'week_start' => '2026-08-03',
        'budget_type' => 'maximum',
        'target_minutes' => 120,
    ]);

    trackMinutes($this->user, $doom, '2026-08-03 09:00', 120);

    $week = $this->periods->weekFromDate($this->user, '2026-08-03');
    $row = fn () => collect($this->budgets->forWeek($this->user, $week)['rows'])
        ->firstWhere('category_id', $doom->id);

    expect($row()['status'])->toBe('on_track');

    trackMinutes($this->user, $doom, '2026-08-03 14:00', 70);

    expect($row()['status'])->toBe('exceeded')
        ->and($row()['difference_minutes'])->toBe(70);
});

it('never scores a reference budget as success or failure', function () {
    $transport = categoryNamed($this->user, 'Transporte');

    $this->user->weeklyBudgets()->create([
        'category_id' => $transport->id,
        'week_start' => '2026-08-03',
        'budget_type' => 'reference',
        'target_minutes' => 300,
    ]);

    trackMinutes($this->user, $transport, '2026-08-03 09:00', 500);

    $week = $this->periods->weekFromDate($this->user, '2026-08-03');
    $row = collect($this->budgets->forWeek($this->user, $week)['rows'])
        ->firstWhere('category_id', $transport->id);

    expect($row['status'])->toBe('reference');
});

it('surfaces the most neglected priority and the biggest overrun', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $training = categoryNamed($this->user, 'Entrenamiento');
    $doom = categoryNamed($this->user, 'Doom Scrolling');

    $this->user->weeklyBudgets()->createMany([
        ['category_id' => $unity->id, 'week_start' => '2026-08-03', 'budget_type' => 'minimum', 'target_minutes' => 600],
        ['category_id' => $training->id, 'week_start' => '2026-08-03', 'budget_type' => 'minimum', 'target_minutes' => 240],
        ['category_id' => $doom->id, 'week_start' => '2026-08-03', 'budget_type' => 'maximum', 'target_minutes' => 120],
    ]);

    trackMinutes($this->user, $unity, '2026-08-03 04:00', 500);
    trackMinutes($this->user, $training, '2026-08-03 13:00', 60);
    trackMinutes($this->user, $doom, '2026-08-03 15:00', 300);

    $week = $this->periods->weekFromDate($this->user, '2026-08-03');
    $report = $this->budgets->forWeek($this->user, $week);

    // Training is short by 180 minutes against Unity's 100.
    expect($report['most_neglected']['category_id'])->toBe($training->id)
        ->and($report['biggest_overrun']['category_id'])->toBe($doom->id)
        ->and($report['biggest_overrun']['difference_minutes'])->toBe(180);
});

it('reports no neglect or overrun when everything is within plan', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $this->user->weeklyBudgets()->create([
        'category_id' => $unity->id,
        'week_start' => '2026-08-03',
        'budget_type' => 'minimum',
        'target_minutes' => 60,
    ]);

    trackMinutes($this->user, $unity, '2026-08-03 04:00', 120);

    $report = $this->budgets->forWeek($this->user, $this->periods->weekFromDate($this->user, '2026-08-03'));

    expect($report['most_neglected'])->toBeNull()
        ->and($report['biggest_overrun'])->toBeNull();
});
