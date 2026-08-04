<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WeeklyReview>
 */
class WeeklyReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'week_start' => CarbonImmutable::now('America/Guatemala')->startOfWeek()->format('Y-m-d'),
            'biggest_time_leak' => $this->faker->sentence(),
            'most_neglected_priority' => $this->faker->sentence(),
            'what_worked' => $this->faker->sentence(),
            'what_did_not_work' => $this->faker->sentence(),
            'next_week_adjustment' => $this->faker->sentence(),
            'notes' => null,
        ];
    }
}
