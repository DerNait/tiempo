<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A half-open instant range [start, end). Both bounds are always absolute
 * instants; the timezone they carry only matters for presentation.
 */
final class Period
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {
    }

    public function seconds(): int
    {
        return max(0, $this->end->getTimestamp() - $this->start->getTimestamp());
    }

    /**
     * Seconds this period shares with another one. Never negative.
     */
    public function overlapSeconds(self $other): int
    {
        $start = max($this->start->getTimestamp(), $other->start->getTimestamp());
        $end = min($this->end->getTimestamp(), $other->end->getTimestamp());

        return max(0, $end - $start);
    }

    public function clampEnd(CarbonImmutable $limit): self
    {
        return $this->end->greaterThan($limit)
            ? new self($this->start, max($this->start, $limit))
            : $this;
    }

    public function contains(CarbonImmutable $instant): bool
    {
        return $instant->greaterThanOrEqualTo($this->start) && $instant->lessThan($this->end);
    }
}
