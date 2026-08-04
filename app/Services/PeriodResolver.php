<?php

namespace App\Services;

use App\Models\User;
use App\Support\Period;
use Carbon\CarbonImmutable;

/**
 * Translates "today" and "this week" for a user into absolute instant ranges.
 * Every boundary is computed in the user's timezone and then kept as an
 * instant, so entries crossing midnight or Monday 00:00 split naturally.
 */
class PeriodResolver
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    public function localNow(User $user): CarbonImmutable
    {
        return $this->now()->setTimezone($user->effectiveTimezone());
    }

    /**
     * Local calendar day containing $reference (defaults to now).
     */
    public function day(User $user, ?CarbonImmutable $reference = null): Period
    {
        $local = ($reference ?? $this->now())->setTimezone($user->effectiveTimezone());
        $start = $local->startOfDay();

        return new Period($start, $start->addDay());
    }

    /**
     * Local week containing $reference. `week_starts_on` uses ISO numbering
     * (1 = Monday), defaulting to Monday.
     */
    public function week(User $user, ?CarbonImmutable $reference = null): Period
    {
        $start = $this->weekStart($user, $reference);

        return new Period($start, $start->addWeek());
    }

    public function weekStart(User $user, ?CarbonImmutable $reference = null): CarbonImmutable
    {
        $local = ($reference ?? $this->now())->setTimezone($user->effectiveTimezone())->startOfDay();
        $weekStartsOn = $user->week_starts_on ?: 1;

        // Carbon: 0 = Sunday .. 6 = Saturday. ISO 7 (Sunday) maps back to 0.
        $target = $weekStartsOn % 7;
        $diff = ($local->dayOfWeek - $target + 7) % 7;

        return $local->subDays($diff);
    }

    /**
     * Week period for an explicit `Y-m-d` week-start date supplied by clients.
     */
    public function weekFromDate(User $user, string $date): Period
    {
        $reference = CarbonImmutable::parse($date, $user->effectiveTimezone())->startOfDay();

        return $this->week($user, $reference);
    }

    public function dayFromDate(User $user, string $date): Period
    {
        $reference = CarbonImmutable::parse($date, $user->effectiveTimezone())->startOfDay();

        return $this->day($user, $reference);
    }
}
