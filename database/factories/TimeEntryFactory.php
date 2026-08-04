<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = CarbonImmutable::now('UTC')->subHours($this->faker->numberBetween(1, 72));
        $endedAt = $startedAt->addMinutes($this->faker->numberBetween(10, 180));

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'description' => null,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => $endedAt->getTimestamp() - $startedAt->getTimestamp(),
            'source' => 'web',
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'ended_at' => null,
            'duration_seconds' => null,
        ]);
    }

    public function between(CarbonImmutable $start, CarbonImmutable $end): static
    {
        return $this->state(fn () => [
            'started_at' => $start,
            'ended_at' => $end,
            'duration_seconds' => $end->getTimestamp() - $start->getTimestamp(),
        ]);
    }
}
