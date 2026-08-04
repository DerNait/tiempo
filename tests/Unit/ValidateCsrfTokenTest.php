<?php

use App\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;

/**
 * CSRF validation is skipped only for token clients that carry no session.
 * Laravel disables CSRF inside HTTP tests, so the rule is asserted directly.
 */
function skipsCsrf(Request $request): bool
{
    $middleware = new ValidateCsrfToken(app(), app('encrypter'));
    $method = new ReflectionMethod($middleware, 'inExceptArray');

    return $method->invoke($middleware, $request);
}

it('skips CSRF for a Bearer request without a session cookie', function () {
    $request = Request::create('/api/rainmeter/status', 'GET');
    $request->headers->set('Authorization', 'Bearer abc123');

    expect(skipsCsrf($request))->toBeTrue();
});

it('still validates CSRF when a session cookie rides along', function () {
    $request = Request::create('/api/tracking/start', 'POST');
    $request->headers->set('Authorization', 'Bearer abc123');
    $request->cookies->set(config('session.cookie'), 'forged-session');

    expect(skipsCsrf($request))->toBeFalse();
});

it('validates CSRF for an ordinary browser request', function () {
    expect(skipsCsrf(Request::create('/api/tracking/start', 'POST')))->toBeFalse();
});
