<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Single source of truth for every "how much time" number in the product.
 * The web reports and the Rainmeter endpoint both go through here so they can
 * never disagree.
 */
class TimeAggregationService
{
    public function __construct(private readonly PeriodResolver $periods)
    {
    }

    /**
     * Entries overlapping the period, eager loading their category.
     *
     * @return Collection<int, TimeEntry>
     */
    public function entriesIn(User $user, Period $period): Collection
    {
        return $user->timeEntries()
            ->with('category')
            ->overlapping($period->start, $period->end)
            ->orderBy('started_at')
            ->get();
    }

    /**
     * The instant range an entry actually occupies, with open entries running
     * up to $now.
     */
    public function entryPeriod(TimeEntry $entry, CarbonImmutable $now): Period
    {
        $start = CarbonImmutable::instance($entry->started_at);
        $end = $entry->ended_at !== null
            ? CarbonImmutable::instance($entry->ended_at)
            : $now;

        if ($end->lessThan($start)) {
            $end = $start;
        }

        return new Period($start, $end);
    }

    /**
     * Seconds tracked inside the period. An entry that crosses the boundary
     * only contributes the slice that falls inside it.
     */
    public function trackedSeconds(User $user, Period $period, ?CarbonImmutable $now = null): int
    {
        $now ??= $this->periods->now();

        return $this->entriesIn($user, $period)->sum(
            fn (TimeEntry $entry) => $period->overlapSeconds($this->entryPeriod($entry, $now))
        );
    }

    /**
     * Tracked seconds keyed by category id.
     *
     * @return array<int, int>
     */
    public function secondsByCategory(User $user, Period $period, ?CarbonImmutable $now = null): array
    {
        $now ??= $this->periods->now();
        $totals = [];

        foreach ($this->entriesIn($user, $period) as $entry) {
            $seconds = $period->overlapSeconds($this->entryPeriod($entry, $now));

            if ($seconds <= 0) {
                continue;
            }

            $totals[$entry->category_id] = ($totals[$entry->category_id] ?? 0) + $seconds;
        }

        arsort($totals);

        return $totals;
    }

    /**
     * Seconds of wall clock that have actually happened inside the period:
     * from its start until now, never past its end.
     */
    public function elapsedSeconds(Period $period, ?CarbonImmutable $now = null): int
    {
        $now ??= $this->periods->now();
        $end = min($period->end->getTimestamp(), $now->getTimestamp());

        return max(0, $end - $period->start->getTimestamp());
    }

    /**
     * Share of elapsed time that is accounted for, as a 0..1 ratio. Overlaps
     * are prevented on write, so this can not exceed 1; it is clamped anyway
     * so legacy data can never render a nonsensical bar.
     */
    public function coverage(User $user, Period $period, ?CarbonImmutable $now = null): float
    {
        $now ??= $this->periods->now();
        $elapsed = $this->elapsedSeconds($period, $now);

        if ($elapsed <= 0) {
            return 0.0;
        }

        return min(1.0, $this->trackedSeconds($user, $period, $now) / $elapsed);
    }

    /**
     * Untracked stretches of the period up to now, as instant ranges.
     *
     * @return list<array{start: string, end: string, seconds: int}>
     */
    public function gaps(User $user, Period $period, ?CarbonImmutable $now = null): array
    {
        $now ??= $this->periods->now();
        $limit = $period->end->greaterThan($now) ? $now : $period->end;

        if ($limit->lessThanOrEqualTo($period->start)) {
            return [];
        }

        $cursor = $period->start;
        $gaps = [];

        foreach ($this->entriesIn($user, $period) as $entry) {
            $entryPeriod = $this->entryPeriod($entry, $now);

            if ($entryPeriod->start->greaterThan($cursor)) {
                $gapEnd = $entryPeriod->start->greaterThan($limit) ? $limit : $entryPeriod->start;

                if ($gapEnd->greaterThan($cursor)) {
                    $gaps[] = $this->gap($cursor, $gapEnd);
                }
            }

            if ($entryPeriod->end->greaterThan($cursor)) {
                $cursor = $entryPeriod->end;
            }
        }

        if ($cursor->lessThan($limit)) {
            $gaps[] = $this->gap($cursor, $limit);
        }

        return $gaps;
    }

    /**
     * @return array{start: string, end: string, seconds: int}
     */
    private function gap(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return [
            'start' => $start->setTimezone('UTC')->toIso8601String(),
            'end' => $end->setTimezone('UTC')->toIso8601String(),
            'seconds' => $end->getTimestamp() - $start->getTimestamp(),
        ];
    }

    /**
     * Per-day tracked seconds across the period, for weekly charts.
     *
     * @return list<array{date: string, seconds: int, elapsed_seconds: int}>
     */
    public function dailyBreakdown(User $user, Period $period, ?CarbonImmutable $now = null): array
    {
        $now ??= $this->periods->now();
        $timezone = $user->effectiveTimezone();
        $cursor = $period->start->setTimezone($timezone)->startOfDay();
        $days = [];

        while ($cursor->lessThan($period->end)) {
            $day = new Period($cursor, $cursor->addDay());
            $days[] = [
                'date' => $cursor->format('Y-m-d'),
                'seconds' => $this->trackedSeconds($user, $day, $now),
                'elapsed_seconds' => $this->elapsedSeconds($day, $now),
            ];
            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
