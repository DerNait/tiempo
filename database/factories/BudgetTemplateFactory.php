<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BudgetTemplate>
 */
class BudgetTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'budget_type' => 'reference',
            'target_minutes' => $this->faker->numberBetween(60, 900),
        ];
    }
}
