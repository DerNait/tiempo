<?php

use App\Services\RainmeterStatusService;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = personalUser();
});

/**
 * Builds the exact fixture the documented sample response describes:
 * Tuesday 2026-08-04, 10:02 local, Unity running for 5010 s.
 */
function rainmeterFixture($user): void
{
    $unity = categoryNamed($user, 'Proyecto de Unity');
    $doom = categoryNamed($user, 'Doom Scrolling');
    $work = categoryNamed($user, 'Trabajo DC');
    $sleep = categoryNamed($user, 'Sueño');

    $user->update([
        'rainmeter_priority_category_id' => $unity->id,
        'rainmeter_leak_category_id' => $doom->id,
    ]);

    $weekStart = '2026-08-03';

    $user->weeklyBudgets()->createMany([
        ['category_id' => $unity->id, 'week_start' => $weekStart, 'budget_type' => 'minimum', 'target_minutes' => 600],
        ['category_id' => $doom->id, 'week_start' => $weekStart, 'budget_type' => 'maximum', 'target_minutes' => 120],
    ]);

    $entry = function ($categoryId, string $from, string $to) use ($user) {
        $start = CarbonImmutable::parse($from, 'America/Guatemala');
        $end = CarbonImmutable::parse($to, 'America/Guatemala');

        $user->timeEntries()->create([
            'category_id' => $categoryId,
            'started_at' => $start->setTimezone('UTC'),
            'ended_at' => $end->setTimezone('UTC'),
            'duration_seconds' => $end->getTimestamp() - $start->getTimestamp(),
            'source' => 'manual',
        ]);
    };

    // Monday: 7h work, 3h55 unity, 1h10 doom, 8h sleep.
    $entry($sleep->id, '2026-08-03 00:00', '2026-08-03 08:00');
    $entry($work->id, '2026-08-03 08:30', '2026-08-03 15:30');
    $entry($unity->id, '2026-08-03 16:00', '2026-08-03 19:55');
    $entry($doom->id, '2026-08-03 20:30', '2026-08-03 21:40');

    // Tuesday up to the open activity: 6h sleep, 1h doom, 1h work.
    $entry($sleep->id, '2026-08-04 00:00', '2026-08-04 06:00');
    $entry($doom->id, '2026-08-04 06:30', '2026-08-04 07:30');
    $entry($work->id, '2026-08-04 07:40', '2026-08-04 08:40');

    // The open activity: started 10:02 local, still running.
    $user->timeEntries()->create([
        'category_id' => $unity->id,
        'started_at' => CarbonImmutable::parse('2026-08-04 10:02', 'America/Guatemala')->setTimezone('UTC'),
        'ended_at' => null,
        'duration_seconds' => null,
        'source' => 'web',
    ]);
}

it('renders every documented key exactly once, in order', function () {
    rainmeterFixture($this->user);

    // 10:02 local + 5010 s = 11:25:30 local = 17:25:30 UTC.
    CarbonImmutable::setTestNow('2026-08-04T17:25:30Z');

    $body = app(RainmeterStatusService::class)->render($this->user->refresh());

    expect($body)->toBe(implode("\n", [
        'ok=1',
        'server_time_unix=1785864330',
        'current_activity_active=1',
        'current_activity_name=Proyecto de Unity',
        'current_activity_started_at=10:02',
        'current_activity_elapsed_seconds=5010',
        'today_tracked_minutes=563',
        'today_elapsed_minutes=685',
        'week_tracked_minutes=1768',
        'week_elapsed_minutes=2125',
        'priority_name=Proyecto de Unity',
        'priority_actual_minutes=318',
        'priority_budget_minutes=600',
        'leak_name=Doom Scrolling',
        'leak_actual_minutes=130',
        'leak_limit_minutes=120',
    ])."\n");
});

it('serves plain text over the HTTP endpoint with a read-only token', function () {
    rainmeterFixture($this->user);
    CarbonImmutable::setTestNow('2026-08-04T17:25:30Z');

    $token = $this->user->createToken('Rainmeter', ['time:read'])->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'text/plain',
    ])->get('/api/rainmeter/status');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('text/plain; charset=UTF-8');

    $lines = explode("\n", rtrim($response->getContent(), "\n"));

    expect($lines)->toHaveCount(16)
        ->and(array_map(fn ($line) => explode('=', $line, 2)[0], $lines))
        ->toBe(App\Services\RainmeterStatusService::KEYS);
});

it('reports zeros and empty strings when nothing is running', function () {
    CarbonImmutable::setTestNow('2026-08-04T06:30:00Z');

    $body = app(RainmeterStatusService::class)->render($this->user);

    expect($body)->toContain('current_activity_active=0')
        ->and($body)->toContain("current_activity_name=\n")
        ->and($body)->toContain("current_activity_started_at=\n")
        ->and($body)->toContain('current_activity_elapsed_seconds=0')
        ->and($body)->toContain("priority_name=\n")
        ->and($body)->toContain('priority_budget_minutes=0')
        ->and($body)->toContain('leak_limit_minutes=0');
});

it('reports a zero budget when the category has none this week', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $this->user->update(['rainmeter_priority_category_id' => $unity->id]);

    CarbonImmutable::setTestNow('2026-08-04T17:00:00Z');

    expect(app(RainmeterStatusService::class)->render($this->user->refresh()))
        ->toContain('priority_name=Proyecto de Unity')
        ->toContain('priority_budget_minutes=0');
});

it('replaces line breaks in category names so the format cannot break', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $unity->update(['name' => "Proyecto\nde\r\nUnity"]);
    $this->user->update(['rainmeter_priority_category_id' => $unity->id]);

    CarbonImmutable::setTestNow('2026-08-04T17:00:00Z');

    $body = app(RainmeterStatusService::class)->render($this->user->refresh());

    expect(explode("\n", rtrim($body, "\n")))->toHaveCount(16)
        ->and($body)->toContain('priority_name=Proyecto de Unity');
});

it('rejects a token without the time:read ability', function () {
    $token = $this->user->createToken('Sin permiso', ['other'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->get('/api/rainmeter/status')
        ->assertForbidden();
});

it('rejects an unauthenticated request', function () {
    $this->getJson('/api/rainmeter/status')->assertUnauthorized();
});

it('stops working once the token is revoked', function () {
    $token = $this->user->createToken('Rainmeter', ['time:read']);
    $plain = $token->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$plain}")
        ->get('/api/rainmeter/status')
        ->assertOk();

    $token->accessToken->delete();

    // A real deployment authenticates each request in a fresh process; inside
    // one test the guard would otherwise hand back its cached user.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$plain}")
        ->getJson('/api/rainmeter/status')
        ->assertUnauthorized();
});

it('never lets a read-only token mutate tracking data', function () {
    $unity = categoryNamed($this->user, 'Proyecto de Unity');
    $token = $this->user->createToken('Rainmeter', ['time:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/tracking/start', ['category_id' => $unity->id])
        ->assertForbidden();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/tracking/stop')
        ->assertForbidden();

    expect($this->user->timeEntries()->count())->toBe(0);
});

it('throttles a token that polls far more often than every 15 minutes', function () {
    Sanctum::actingAs($this->user, ['time:read']);

    foreach (range(1, 30) as $ignored) {
        $this->get('/api/rainmeter/status')->assertOk();
    }

    $this->get('/api/rainmeter/status')->assertStatus(429);
});
