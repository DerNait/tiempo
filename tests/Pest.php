<?php

use App\Models\Category;
use App\Models\User;
use App\Services\CategoryProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * A user in America/Guatemala (UTC-6, no DST) with the default categories,
 * which is what every timing assertion in this suite is written against.
 */
function personalUser(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'timezone' => 'America/Guatemala',
        'week_starts_on' => 1,
    ], $attributes));

    app(CategoryProvisioner::class)->seedDefaults($user);

    return $user->refresh();
}

function categoryNamed(User $user, string $name): Category
{
    return $user->categories()->where('name', $name)->firstOrFail();
}
