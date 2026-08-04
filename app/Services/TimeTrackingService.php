<?php

namespace App\Services;

use App\Exceptions\OverlappingEntryException;
use App\Exceptions\TrackingConflictException;
use App\Models\Category;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Every write that can create or close an activity funnels through here so the
 * "exactly one open entry, no overlaps" invariant lives in one place.
 */
class TimeTrackingService
{
    public function __construct(private readonly PeriodResolver $periods)
    {
    }

    /**
     * Start a new activity, atomically closing the previous one at the same
     * instant so no time is double counted and no gap is invented.
     */
    public function start(
        User $user,
        Category $category,
        ?string $description = null,
        string $source = 'web',
        ?CarbonImmutable $at = null,
    ): TimeEntry {
        return DB::transaction(function () use ($user, $category, $description, $source, $at) {
            $this->lockUser($user);

            $at = ($at ?? $this->periods->now())->setTimezone('UTC');
            $open = $this->lockedOpenEntry($user);

            if ($open !== null) {
                if ($at->lessThan($open->started_at)) {
                    throw new TrackingConflictException(
                        'La hora de inicio es anterior al comienzo de la actividad abierta.'
                    );
                }

                $elapsed = $at->getTimestamp() - $open->started_at->getTimestamp();

                if ($elapsed <= 0) {
                    // Two taps inside the same second: the first one never
                    // represented real time, so drop it instead of storing a
                    // zero-length entry.
                    if ($open->category_id === $category->id) {
                        return $open;
                    }

                    $open->delete();
                } else {
                    $this->close($open, $at);
                }
            }

            $this->assertNoOverlap($user, $at, null);

            return $user->timeEntries()->create([
                'category_id' => $category->id,
                'description' => $description,
                'started_at' => $at,
                'ended_at' => null,
                'duration_seconds' => null,
                'source' => $source,
            ]);
        });
    }

    /**
     * Close the open activity. Returns null when nothing was running.
     */
    public function stop(User $user, ?CarbonImmutable $at = null): ?TimeEntry
    {
        return DB::transaction(function () use ($user, $at) {
            $this->lockUser($user);

            $open = $this->lockedOpenEntry($user);

            if ($open === null) {
                return null;
            }

            $at = ($at ?? $this->periods->now())->setTimezone('UTC');

            if ($at->lessThan($open->started_at)) {
                throw new TrackingConflictException(
                    'La hora de fin es anterior al inicio de la actividad.'
                );
            }

            if ($at->getTimestamp() === $open->started_at->getTimestamp()) {
                $open->delete();

                return null;
            }

            $this->close($open, $at);

            return $open->refresh();
        });
    }

    public function createManual(User $user, array $attributes): TimeEntry
    {
        return DB::transaction(function () use ($user, $attributes) {
            $this->lockUser($user);

            $startedAt = CarbonImmutable::parse($attributes['started_at'])->setTimezone('UTC');
            $endedAt = isset($attributes['ended_at']) && $attributes['ended_at'] !== null
                ? CarbonImmutable::parse($attributes['ended_at'])->setTimezone('UTC')
                : null;

            if ($endedAt !== null && $endedAt->lessThanOrEqualTo($startedAt)) {
                throw new TrackingConflictException('La hora de fin debe ser posterior a la de inicio.');
            }

            if ($endedAt === null && $this->lockedOpenEntry($user) !== null) {
                throw new TrackingConflictException(
                    'Ya existe una actividad abierta. Detenla antes de crear otra sin hora de fin.'
                );
            }

            $this->assertNoOverlap($user, $startedAt, $endedAt);

            return $user->timeEntries()->create([
                'category_id' => $attributes['category_id'],
                'description' => $attributes['description'] ?? null,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'duration_seconds' => $endedAt !== null
                    ? $endedAt->getTimestamp() - $startedAt->getTimestamp()
                    : null,
                'source' => $attributes['source'] ?? 'manual',
            ]);
        });
    }

    public function updateEntry(TimeEntry $entry, array $attributes): TimeEntry
    {
        return DB::transaction(function () use ($entry, $attributes) {
            $user = $entry->user;
            $this->lockUser($user);

            $startedAt = array_key_exists('started_at', $attributes)
                ? CarbonImmutable::parse($attributes['started_at'])->setTimezone('UTC')
                : CarbonImmutable::instance($entry->started_at);

            $endedAt = array_key_exists('ended_at', $attributes)
                ? ($attributes['ended_at'] === null
                    ? null
                    : CarbonImmutable::parse($attributes['ended_at'])->setTimezone('UTC'))
                : ($entry->ended_at !== null ? CarbonImmutable::instance($entry->ended_at) : null);

            if ($endedAt !== null && $endedAt->lessThanOrEqualTo($startedAt)) {
                throw new TrackingConflictException('La hora de fin debe ser posterior a la de inicio.');
            }

            if ($endedAt === null) {
                $otherOpen = $user->timeEntries()
                    ->whereNull('ended_at')
                    ->whereKeyNot($entry->getKey())
                    ->exists();

                if ($otherOpen) {
                    throw new TrackingConflictException('Solo puede haber una actividad abierta.');
                }
            }

            $this->assertNoOverlap($user, $startedAt, $endedAt, $entry->getKey());

            $entry->fill(array_filter([
                'category_id' => $attributes['category_id'] ?? null,
                'source' => $attributes['source'] ?? null,
            ], fn ($value) => $value !== null));

            if (array_key_exists('description', $attributes)) {
                $entry->description = $attributes['description'];
            }

            $entry->started_at = $startedAt;
            $entry->ended_at = $endedAt;
            $entry->duration_seconds = $endedAt !== null
                ? $endedAt->getTimestamp() - $startedAt->getTimestamp()
                : null;
            $entry->save();

            return $entry;
        });
    }

    /**
     * Reject any entry that would share wall clock time with another one.
     * Open entries are treated as running to infinity, so nothing may start
     * after them either.
     */
    public function assertNoOverlap(
        User $user,
        CarbonImmutable $startedAt,
        ?CarbonImmutable $endedAt,
        ?int $ignoreId = null,
    ): void {
        $query = $user->timeEntries()->with('category');

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        // An unfinished new entry conflicts with everything after its start.
        $rangeEnd = $endedAt ?? CarbonImmutable::create(9999, 12, 31, 0, 0, 0, 'UTC');

        $conflict = $query->overlapping($startedAt, $rangeEnd)->orderBy('started_at')->first();

        if ($conflict !== null) {
            throw new OverlappingEntryException($conflict);
        }
    }

    private function close(TimeEntry $entry, CarbonImmutable $at): void
    {
        $entry->forceFill([
            'ended_at' => $at,
            'duration_seconds' => $at->getTimestamp() - $entry->started_at->getTimestamp(),
        ])->save();
    }

    /**
     * Serialize concurrent tracking writes for one user. On SQLite this is a
     * no-op statement, which is fine: the test suite runs single connection.
     */
    private function lockUser(User $user): void
    {
        User::query()->whereKey($user->getKey())->lockForUpdate()->first();
    }

    private function lockedOpenEntry(User $user): ?TimeEntry
    {
        return $user->timeEntries()
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->lockForUpdate()
            ->first();
    }
}
