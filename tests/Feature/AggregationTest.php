<?php

use App\Services\PeriodResolver;
use App\Services\TimeAggregationService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->user = personalUser();
    $this->aggregation = app(TimeAggregationService::class);
    $this->periods = app(PeriodResolver::class);
});

/** Guatemala is UTC-6 all year, so local midnight is 06:00 UTC. */
it('resolves the local day boundary in the user timezone', function () {
    CarbonImmutable::setTestNow('2026-08-04T16:00:00Z');

    $day = $this->periods->day($this->user);

    expect($day->start->setTimezone('UTC')->toIso8601String())->toBe('2026-08-04T06:00:00+00:00')
        ->and($day->end->setTimezone('UTC')->toIso8601String())->toBe('2026-08-05T06:00:00+00:00');
});

it('starts the week on the local Monday', function () {
    // Tuesday 2026-08-04 local.
    CarbonImmutable::setTestNow('2026-08-04T16:00:00Z');

    $week = $this->periods->week($this->user);

    expect($week->start->format('Y-m-d'))->toBe('2026-08-03')
        ->and($week->start->setTimezone('UTC')->toIso8601String())->toBe('2026-08-03T06:00:00+00:00');
});

it('splits an entry that crosses local midnight between both days', function () {
    $sleep = categoryNamed($this->user, 'Sueño');

    // 22:00 → 06:00 local, i.e. two hours on the 4th and six on the 5th.
    $this->user->timeEntries()->create([
        'category_id' => $sleep->id,
        'started_at' => CarbonImmutable::parse('2026-08-05T04:00:00Z'),
        'ended_at' => CarbonImmutable::parse('2026-08-05T12:00:00Z'),
        'duration_seconds' => 8 * 3600,
        'source' => 'manual',
    ]);

    CarbonImmutable::setTestNow('2026-08-05T18:00:00Z');

    $fourth = $this->periods->dayFromDate($this->user, '2026-08-04');
    $fifth = $this->periods->dayFromDate($this->user, '2026-08-05');

    expect($this->aggregation->trackedSeconds($this->user, $fourth))->toBe(2 * 3600)
        ->and($this->aggregation->trackedSeconds($this->user, $fifth))->toBe(6 * 3600);
});

it('splits an entry that crosses the start of the week', function () {
    $sleep = categoryNamed($this->user, 'Sueño');

    // Sunday 22:00 → Monday 06:00 local.
    $this->user->timeEntries()->create([
        'category_id' => $sleep->id,
        'started_at' => CarbonImmutable::parse('2026-08-03T04:00:00Z'),
        'ended_at' => CarbonImmutable::parse('2026-08-03T12:00:00Z'),
        'duration_seconds' => 8 * 3600,
        'source' => 'manual',
    ]);

    CarbonImmutable::setTestNow('2026-08-05T18:00:00Z');

    $thisWeek = $this->periods->weekFromDate($this->user, '2026-08-03');
    $lastWeek = $this->periods->weekFromDate($this->user, '2026-07-27');

    expect($this->aggregation->trackedSeconds($this->user, $thisWeek))->toBe(6 * 3600)
        ->and($this->aggregation->trackedSeconds($this->user, $lastWeek))->toBe(2 * 3600);
});

it('counts an open entry up to now', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $this->user->timeEntries()->create([
        'category_id' => $unity->id,
        'started_at' => CarbonImmutable::parse('2026-08-04T15:00:00Z'),
        'ended_at' => null,
        'duration_seconds' => null,
        'source' => 'web',
    ]);

    CarbonImmutable::setTestNow('2026-08-04T16:30:00Z');

    $today = $this->periods->day($this->user);

    expect($this->aggregation->trackedSeconds($this->user, $today))->toBe(5400);

    CarbonImmutable::setTestNow('2026-08-04T17:00:00Z');

    expect($this->aggregation->trackedSeconds($this->user, $today))->toBe(7200);
});

it('never counts an open entry past the end of the period', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    // Started 23:00 local on the 4th, still running the next day.
    $this->user->timeEntries()->create([
        'category_id' => $unity->id,
        'started_at' => CarbonImmutable::parse('2026-08-05T05:00:00Z'),
        'ended_at' => null,
        'duration_seconds' => null,
        'source' => 'web',
    ]);

    CarbonImmutable::setTestNow('2026-08-05T14:00:00Z');

    $fourth = $this->periods->dayFromDate($this->user, '2026-08-04');

    expect($this->aggregation->trackedSeconds($this->user, $fourth))->toBe(3600);
});

it('reports coverage against elapsed time and never exceeds 100%', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    // Local midnight → 03:00, with "now" at 06:00 local.
    $this->user->timeEntries()->create([
        'category_id' => $unity->id,
        'started_at' => CarbonImmutable::parse('2026-08-04T06:00:00Z'),
        'ended_at' => CarbonImmutable::parse('2026-08-04T09:00:00Z'),
        'duration_seconds' => 3 * 3600,
        'source' => 'manual',
    ]);

    CarbonImmutable::setTestNow('2026-08-04T12:00:00Z');

    $today = $this->periods->day($this->user);

    expect($this->aggregation->elapsedSeconds($today))->toBe(6 * 3600)
        ->and($this->aggregation->coverage($this->user, $today))->toBe(0.5);
});

it('lists untracked gaps up to now', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $this->user->timeEntries()->create([
        'category_id' => $unity->id,
        'started_at' => CarbonImmutable::parse('2026-08-04T07:00:00Z'),
        'ended_at' => CarbonImmutable::parse('2026-08-04T08:00:00Z'),
        'duration_seconds' => 3600,
        'source' => 'manual',
    ]);

    CarbonImmutable::setTestNow('2026-08-04T10:00:00Z');

    $gaps = $this->aggregation->gaps($this->user, $this->periods->day($this->user));

    expect($gaps)->toHaveCount(2)
        ->and($gaps[0]['seconds'])->toBe(3600)
        ->and($gaps[1]['seconds'])->toBe(2 * 3600);
});

it('groups tracked seconds by category', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $doom = categoryNamed($this->user, 'Doom Scrolling');

    $this->user->timeEntries()->createMany([
        [
            'category_id' => $unity->id,
            'started_at' => CarbonImmutable::parse('2026-08-04T07:00:00Z'),
            'ended_at' => CarbonImmutable::parse('2026-08-04T09:00:00Z'),
            'duration_seconds' => 7200,
            'source' => 'manual',
        ],
        [
            'category_id' => $doom->id,
            'started_at' => CarbonImmutable::parse('2026-08-04T09:00:00Z'),
            'ended_at' => CarbonImmutable::parse('2026-08-04T09:30:00Z'),
            'duration_seconds' => 1800,
            'source' => 'manual',
        ],
    ]);

    CarbonImmutable::setTestNow('2026-08-04T12:00:00Z');

    $totals = $this->aggregation->secondsByCategory($this->user, $this->periods->day($this->user));

    expect($totals[$unity->id])->toBe(7200)
        ->and($totals[$doom->id])->toBe(1800)
        // Sorted descending so the heaviest category leads every report.
        ->and(array_key_first($totals))->toBe($unity->id);
});

it('keeps a different timezone consistent with its own midnight', function () {
    $madrid = personalUser(['timezone' => 'Europe/Madrid']);
    $unity = categoryNamed($madrid, 'Proyecto de Unity');

    // 01:00 local Madrid (UTC+2 in August) on the 5th.
    $madrid->timeEntries()->create([
        'category_id' => $unity->id,
        'started_at' => CarbonImmutable::parse('2026-08-04T23:00:00Z'),
        'ended_at' => CarbonImmutable::parse('2026-08-05T00:00:00Z'),
        'duration_seconds' => 3600,
        'source' => 'manual',
    ]);

    CarbonImmutable::setTestNow('2026-08-05T10:00:00Z');

    expect($this->aggregation->trackedSeconds($madrid, $this->periods->dayFromDate($madrid, '2026-08-05')))->toBe(3600)
        ->and($this->aggregation->trackedSeconds($madrid, $this->periods->dayFromDate($madrid, '2026-08-04')))->toBe(0);
});
