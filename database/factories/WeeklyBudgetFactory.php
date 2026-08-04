<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WeeklyBudget>
 */
class WeeklyBudgetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'week_start' => CarbonImmutable::now('America/Guatemala')->startOfWeek()->format('Y-m-d'),
            'budget_type' => 'reference',
            'target_minutes' => $this->faker->numberBetween(60, 900),
        ];
    }

    public function minimum(int $minutes): static
    {
        return $this->state(fn () => ['budget_type' => 'minimum', 'target_minutes' => $minutes]);
    }

    public function maximum(int $minutes): static
    {
        return $this->state(fn () => ['budget_type' => 'maximum', 'target_minutes' => $minutes]);
    }
}
