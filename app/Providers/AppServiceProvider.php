<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\TimeEntry;
use App\Policies\CategoryPolicy;
use App\Policies\TimeEntryPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(TimeEntry::class, TimeEntryPolicy::class);

        // The skin polls every 15 minutes; this leaves ample headroom for
        // manual refreshes while still bounding a leaked token.
        RateLimiter::for('rainmeter', fn (Request $request) => Limit::perMinute(30)
            ->by($request->user()?->currentAccessToken()?->id ?? $request->ip()));

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
