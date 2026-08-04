<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as Middleware;

class ValidateCsrfToken extends Middleware
{
    /**
     * The app routes run inside the `web` group so the SPA gets a session and
     * CSRF protection. A Bearer-token client has no session to forge, and
     * failing it here would hide the real reason it is rejected: the missing
     * `time:write` ability.
     *
     * The session cookie check keeps this from becoming a bypass — a
     * cross-site forgery always rides on the victim's cookie, so a request
     * carrying one is still validated even if it also sets an Authorization
     * header.
     */
    protected function inExceptArray($request): bool
    {
        if ($request->bearerToken() !== null && ! $request->hasCookie(config('session.cookie'))) {
            return true;
        }

        return parent::inExceptArray($request);
    }
}
