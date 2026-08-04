<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(2, true));

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'group_name' => $this->faker->randomElement(['Trabajo', 'Universidad', 'Salud y mantenimiento']),
            'icon' => '⏱️',
            'color' => $this->faker->hexColor(),
            'sort_order' => $this->faker->numberBetween(1, 50),
            'is_active' => true,
            'is_favorite' => false,
        ];
    }

    public function favorite(): static
    {
        return $this->state(fn () => ['is_favorite' => true]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
