<?php

use App\Exceptions\OverlappingEntryException;
use App\Exceptions\TrackingConflictException;
use App\Models\TimeEntry;
use App\Services\TimeTrackingService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->user = personalUser();
    $this->tracking = app(TimeTrackingService::class);
});

it('keeps at most one open entry per user', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $doom = categoryNamed($this->user, 'Doom Scrolling');

    CarbonImmutable::setTestNow('2026-08-04T15:00:00Z');
    $this->tracking->start($this->user, $unity);

    CarbonImmutable::setTestNow('2026-08-04T16:00:00Z');
    $this->tracking->start($this->user, $doom);

    expect($this->user->timeEntries()->whereNull('ended_at')->count())->toBe(1);
    expect($this->user->openTimeEntry()->category_id)->toBe($doom->id);
});

it('closes the previous activity at the exact switch instant', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $doom = categoryNamed($this->user, 'Doom Scrolling');

    CarbonImmutable::setTestNow('2026-08-04T15:00:00Z');
    $first = $this->tracking->start($this->user, $unity);

    CarbonImmutable::setTestNow('2026-08-04T16:00:00Z');
    $second = $this->tracking->start($this->user, $doom);

    $first->refresh();

    expect($first->ended_at->toIso8601String())->toBe($second->started_at->toIso8601String())
        ->and($first->duration_seconds)->toBe(3600);
});

it('leaves exactly one activity running when two starts land in the same second', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $doom = categoryNamed($this->user, 'Doom Scrolling');

    CarbonImmutable::setTestNow('2026-08-04T15:00:00Z');

    $this->tracking->start($this->user, $unity);
    $this->tracking->start($this->user, $doom);

    // The first tap never covered real time, so it is dropped rather than
    // stored as a zero-length entry.
    expect($this->user->timeEntries()->count())->toBe(1)
        ->and($this->user->openTimeEntry()->category_id)->toBe($doom->id);
});

it('is idempotent when the same category is tapped twice in one second', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    CarbonImmutable::setTestNow('2026-08-04T15:00:00Z');

    $first = $this->tracking->start($this->user, $unity);
    $second = $this->tracking->start($this->user, $unity);

    expect($second->id)->toBe($first->id)
        ->and($this->user->timeEntries()->count())->toBe(1);
});

it('does nothing when stopping with no activity open', function () {
    expect($this->tracking->stop($this->user))->toBeNull()
        ->and($this->user->timeEntries()->count())->toBe(0);
});

it('closes the running activity with the server clock', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    CarbonImmutable::setTestNow('2026-08-04T15:00:00Z');
    $this->tracking->start($this->user, $unity);

    CarbonImmutable::setTestNow('2026-08-04T15:45:00Z');
    $stopped = $this->tracking->stop($this->user);

    expect($stopped->duration_seconds)->toBe(2700)
        ->and($this->user->openTimeEntry())->toBeNull();
});

it('rejects a manual entry that overlaps an existing one', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $doom = categoryNamed($this->user, 'Doom Scrolling');

    $this->tracking->createManual($this->user, [
        'category_id' => $unity->id,
        'started_at' => '2026-08-04T15:00:00Z',
        'ended_at' => '2026-08-04T17:00:00Z',
    ]);

    $this->tracking->createManual($this->user, [
        'category_id' => $doom->id,
        'started_at' => '2026-08-04T16:00:00Z',
        'ended_at' => '2026-08-04T18:00:00Z',
    ]);
})->throws(OverlappingEntryException::class);

it('allows a manual entry that only touches the previous boundary', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $doom = categoryNamed($this->user, 'Doom Scrolling');

    $this->tracking->createManual($this->user, [
        'category_id' => $unity->id,
        'started_at' => '2026-08-04T15:00:00Z',
        'ended_at' => '2026-08-04T17:00:00Z',
    ]);

    $second = $this->tracking->createManual($this->user, [
        'category_id' => $doom->id,
        'started_at' => '2026-08-04T17:00:00Z',
        'ended_at' => '2026-08-04T18:00:00Z',
    ]);

    expect($second->duration_seconds)->toBe(3600)
        ->and($this->user->timeEntries()->count())->toBe(2);
});

it('rejects an edit that would create an overlap', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $doom = categoryNamed($this->user, 'Doom Scrolling');

    $first = $this->tracking->createManual($this->user, [
        'category_id' => $unity->id,
        'started_at' => '2026-08-04T15:00:00Z',
        'ended_at' => '2026-08-04T16:00:00Z',
    ]);

    $this->tracking->createManual($this->user, [
        'category_id' => $doom->id,
        'started_at' => '2026-08-04T16:00:00Z',
        'ended_at' => '2026-08-04T17:00:00Z',
    ]);

    $this->tracking->updateEntry($first, ['ended_at' => '2026-08-04T16:30:00Z']);
})->throws(OverlappingEntryException::class);

it('refuses a second open entry through manual creation', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $doom = categoryNamed($this->user, 'Doom Scrolling');

    CarbonImmutable::setTestNow('2026-08-04T15:00:00Z');
    $this->tracking->start($this->user, $unity);

    $this->tracking->createManual($this->user, [
        'category_id' => $doom->id,
        'started_at' => '2026-08-04T10:00:00Z',
        'ended_at' => null,
    ]);
})->throws(TrackingConflictException::class);

it('refuses an end before the start when editing', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $entry = $this->tracking->createManual($this->user, [
        'category_id' => $unity->id,
        'started_at' => '2026-08-04T15:00:00Z',
        'ended_at' => '2026-08-04T16:00:00Z',
    ]);

    $this->tracking->updateEntry($entry, ['ended_at' => '2026-08-04T14:00:00Z']);
})->throws(TrackingConflictException::class);

it('materialises duration when an entry is edited', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $entry = $this->tracking->createManual($this->user, [
        'category_id' => $unity->id,
        'started_at' => '2026-08-04T15:00:00Z',
        'ended_at' => '2026-08-04T16:00:00Z',
    ]);

    $updated = $this->tracking->updateEntry($entry, ['ended_at' => '2026-08-04T17:30:00Z']);

    expect($updated->duration_seconds)->toBe(9000)
        ->and(TimeEntry::find($entry->id)->duration_seconds)->toBe(9000);
});
