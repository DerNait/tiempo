<?php

use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->user = personalUser();
    CarbonImmutable::setTestNow('2026-08-04T17:00:00Z');
});

it('rejects anonymous access to the app API', function () {
    $this->getJson('/api/status')->assertUnauthorized();
});

it('returns the current status with today and week totals', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $response = $this->actingAs($this->user)
        ->postJson('/api/tracking/start', ['category_id' => $unity->id])
        ->assertOk();

    $response->assertJsonPath('current_entry.category_id', $unity->id)
        ->assertJsonPath('timezone', 'America/Guatemala')
        ->assertJsonPath('week.week_start', '2026-08-03')
        ->assertJsonStructure(['today' => ['tracked_seconds', 'elapsed_seconds', 'coverage'], 'favorites']);
});

it('switches activity in a single request', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $doom = categoryNamed($this->user, 'Doom Scrolling');

    $this->actingAs($this->user)->postJson('/api/tracking/start', ['category_id' => $unity->id])->assertOk();

    CarbonImmutable::setTestNow('2026-08-04T18:00:00Z');

    $this->actingAs($this->user)
        ->postJson('/api/tracking/start', ['category_id' => $doom->id])
        ->assertOk()
        ->assertJsonPath('current_entry.category_id', $doom->id);

    expect($this->user->timeEntries()->whereNull('ended_at')->count())->toBe(1);
});

it('stops the running activity', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $this->actingAs($this->user)->postJson('/api/tracking/start', ['category_id' => $unity->id]);

    CarbonImmutable::setTestNow('2026-08-04T18:00:00Z');

    $this->actingAs($this->user)
        ->postJson('/api/tracking/stop')
        ->assertOk()
        ->assertJsonPath('current_entry', null);
});

it('refuses to start a category owned by someone else', function () {
    $other = personalUser();
    $foreign = categoryNamed($other, 'Proyecto de Unity');

    $this->actingAs($this->user)
        ->postJson('/api/tracking/start', ['category_id' => $foreign->id])
        ->assertStatus(422);
});

it('returns a 422 with the conflicting entry when a manual range overlaps', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $doom = categoryNamed($this->user, 'Doom Scrolling');

    $this->actingAs($this->user)->postJson('/api/time-entries', [
        'category_id' => $unity->id,
        'started_at' => '2026-08-04T15:00:00Z',
        'ended_at' => '2026-08-04T16:00:00Z',
    ])->assertCreated();

    $this->actingAs($this->user)->postJson('/api/time-entries', [
        'category_id' => $doom->id,
        'started_at' => '2026-08-04T15:30:00Z',
        'ended_at' => '2026-08-04T16:30:00Z',
    ])->assertStatus(422)->assertJsonPath('code', 'overlapping_entry');
});

it('cannot edit or delete another user\'s entry', function () {
    $other = personalUser();
    $entry = $other->timeEntries()->create([
        'category_id' => categoryNamed($other, 'Ocio')->id,
        'started_at' => CarbonImmutable::parse('2026-08-04T10:00:00Z'),
        'ended_at' => CarbonImmutable::parse('2026-08-04T11:00:00Z'),
        'duration_seconds' => 3600,
        'source' => 'manual',
    ]);

    $this->actingAs($this->user)->patchJson("/api/time-entries/{$entry->id}", ['description' => 'x'])
        ->assertForbidden();

    $this->actingAs($this->user)->deleteJson("/api/time-entries/{$entry->id}")->assertForbidden();
});

it('archives a category that already has entries instead of deleting it', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $this->actingAs($this->user)->postJson('/api/time-entries', [
        'category_id' => $unity->id,
        'started_at' => '2026-08-04T15:00:00Z',
        'ended_at' => '2026-08-04T16:00:00Z',
    ])->assertCreated();

    $this->actingAs($this->user)
        ->deleteJson("/api/categories/{$unity->id}")
        ->assertOk()
        ->assertJsonPath('archived', true);

    expect($unity->refresh()->is_active)->toBeFalse();
});

it('saves and reads back a weekly budget', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $this->actingAs($this->user)->postJson('/api/budgets', [
        'week_start' => '2026-08-03',
        'budgets' => [
            ['category_id' => $unity->id, 'budget_type' => 'minimum', 'target_minutes' => 600],
        ],
        'save_as_template' => true,
    ])->assertOk();

    expect($this->user->weeklyBudgets()->count())->toBe(1)
        ->and($this->user->budgetTemplates()->count())->toBe(1);

    $this->actingAs($this->user)
        ->getJson('/api/budgets?week_start=2026-08-03')
        ->assertOk()
        ->assertJsonPath('week_start', '2026-08-03');
});

it('copies the previous week budget forward', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $this->user->weeklyBudgets()->create([
        'category_id' => $unity->id,
        'week_start' => '2026-07-27',
        'budget_type' => 'minimum',
        'target_minutes' => 480,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/budgets/copy-previous', ['week_start' => '2026-08-03'])
        ->assertOk()
        ->assertJsonPath('copied', 1);

    expect($this->user->weeklyBudgets()->where('week_start', '2026-08-03')->first()->target_minutes)->toBe(480);
});

it('stores one weekly review per week', function () {
    $this->actingAs($this->user)->postJson('/api/weekly-review', [
        'week_start' => '2026-08-03',
        'what_worked' => 'Registrar al cambiar de tarea.',
    ])->assertOk();

    $this->actingAs($this->user)->postJson('/api/weekly-review', [
        'week_start' => '2026-08-03',
        'what_worked' => 'Corregido.',
    ])->assertOk();

    expect($this->user->weeklyReviews()->count())->toBe(1)
        ->and($this->user->weeklyReviews()->first()->what_worked)->toBe('Corregido.');
});

it('exports the history as CSV', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $this->user->timeEntries()->create([
        'category_id' => $unity->id,
        'started_at' => CarbonImmutable::parse('2026-08-04T15:00:00Z'),
        'ended_at' => CarbonImmutable::parse('2026-08-04T16:00:00Z'),
        'duration_seconds' => 3600,
        'source' => 'manual',
    ]);

    $response = $this->actingAs($this->user)->get('/api/time-entries/export');
    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->toContain('Proyecto de Unity')
        // 15:00 UTC is 09:00 in Guatemala.
        ->toContain('2026-08-04 09:00:00')
        ->toContain('60');
});

it('shows a created token only once and can revoke it', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/tokens', ['name' => 'Rainmeter'])
        ->assertCreated();

    $tokenId = $response->json('id');
    expect($response->json('token'))->toBeString()
        ->and($response->json('abilities'))->toBe(['time:read']);

    $this->actingAs($this->user)->getJson('/api/tokens')
        ->assertOk()
        ->assertJsonMissing(['token' => $response->json('token')]);

    $this->actingAs($this->user)->deleteJson("/api/tokens/{$tokenId}")
        ->assertOk()
        ->assertJsonPath('revoked', true);
});

it('updates settings and stamps the audit start', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/settings', ['audit_mode_enabled' => true, 'onboarded' => true])
        ->assertOk()
        ->assertJsonPath('user.audit_mode_enabled', true)
        ->assertJsonPath('user.onboarded', true);

    expect(User::find($this->user->id)->audit_started_at)->not->toBeNull();
});

it('returns a day report with timeline and gaps', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');

    $this->user->timeEntries()->create([
        'category_id' => $unity->id,
        'started_at' => CarbonImmutable::parse('2026-08-04T07:00:00Z'),
        'ended_at' => CarbonImmutable::parse('2026-08-04T08:00:00Z'),
        'duration_seconds' => 3600,
        'source' => 'manual',
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/reports/day')
        ->assertOk()
        ->assertJsonPath('date', '2026-08-04')
        ->assertJsonPath('tracked_seconds', 3600)
        ->assertJsonCount(1, 'timeline')
        ->assertJsonStructure(['gaps' => [['start', 'end', 'seconds']]]);
});

it('returns a week report with budget and previous week comparison', function () {
    $this->actingAs($this->user)
        ->getJson('/api/reports/week')
        ->assertOk()
        ->assertJsonPath('week_start', '2026-08-03')
        ->assertJsonPath('week_end', '2026-08-09')
        ->assertJsonCount(7, 'daily')
        ->assertJsonStructure(['budget' => ['rows'], 'previous_week' => ['week_start', 'tracked_seconds']]);
});

it('logs in and out through the session endpoints', function () {
    $user = User::factory()->create(['email' => 'yo@example.com', 'password' => 'secreto-largo']);

    $this->postJson('/api/login', ['email' => 'yo@example.com', 'password' => 'malo'])
        ->assertStatus(422);

    $this->postJson('/api/login', ['email' => 'yo@example.com', 'password' => 'secreto-largo'])
        ->assertOk()
        ->assertJsonPath('user.email', 'yo@example.com');

    $this->actingAs($user)->postJson('/api/logout')->assertOk();
});

it('schedules the audit for a future day without counting it yet', function () {
    // Late on 4 August local (22:00), scheduling the audit to begin on the 5th.
    CarbonImmutable::setTestNow('2026-08-05T04:00:00Z');

    $this->actingAs($this->user)
        ->patchJson('/api/settings', [
            'audit_mode_enabled' => true,
            'audit_start_date' => '2026-08-05',
        ])
        ->assertOk()
        ->assertJsonPath('user.audit_start_date', '2026-08-05');

    // Stored as local midnight, which is 06:00 UTC in Guatemala.
    expect($this->user->fresh()->audit_started_at->toIso8601String())->toBe('2026-08-05T06:00:00+00:00');

    $this->actingAs($this->user)
        ->getJson('/api/status')
        ->assertOk()
        ->assertJsonPath('audit.pending', true)
        ->assertJsonPath('audit.day_number', 0)
        ->assertJsonPath('audit.starts_on', '2026-08-05')
        ->assertJsonPath('audit.finished', false);
});

it('counts audit days as whole local days once it begins', function () {
    $this->actingAs($this->user)->patchJson('/api/settings', [
        'audit_mode_enabled' => true,
        'audit_start_date' => '2026-08-05',
        'audit_days' => 7,
    ])->assertOk();

    // First local day, one minute past midnight.
    CarbonImmutable::setTestNow('2026-08-05T06:01:00Z');
    $this->actingAs($this->user)->getJson('/api/status')
        ->assertJsonPath('audit.pending', false)
        ->assertJsonPath('audit.day_number', 1);

    // Late on the same local day: still day 1, not day 2.
    CarbonImmutable::setTestNow('2026-08-06T05:00:00Z');
    $this->actingAs($this->user)->getJson('/api/status')->assertJsonPath('audit.day_number', 1);

    // Just after the next local midnight: day 2.
    CarbonImmutable::setTestNow('2026-08-06T06:30:00Z');
    $this->actingAs($this->user)->getJson('/api/status')->assertJsonPath('audit.day_number', 2);

    // Seventh local day.
    CarbonImmutable::setTestNow('2026-08-11T12:00:00Z');
    $this->actingAs($this->user)->getJson('/api/status')
        ->assertJsonPath('audit.day_number', 7)
        ->assertJsonPath('audit.finished', false);

    // Past local midnight after the last day.
    CarbonImmutable::setTestNow('2026-08-12T06:30:00Z');
    $this->actingAs($this->user)->getJson('/api/status')->assertJsonPath('audit.finished', true);
});

it('keeps an explicit start date instead of stamping now', function () {
    CarbonImmutable::setTestNow('2026-08-04T19:15:00Z');

    $this->actingAs($this->user)->patchJson('/api/settings', [
        'audit_mode_enabled' => true,
        'audit_start_date' => '2026-08-10',
    ])->assertOk();

    expect($this->user->fresh()->audit_started_at->toIso8601String())->toBe('2026-08-10T06:00:00+00:00');
});
