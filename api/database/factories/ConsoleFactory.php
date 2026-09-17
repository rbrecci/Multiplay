<?php

namespace Database\Factories;

use App\Models\Console;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Console>
 */
class ConsoleFactory extends Factory
{
    protected $model = Console::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'manufacturer' => fake()->company(),
            'release_year' => fake()->numberBetween(1980, 2024),
            'sort_order' => fake()->unique()->numberBetween(1, 1000),
            'igdb_platform_id' => fake()->numberBetween(1, 500),
        ];
    }

    public function ownedBy(User $user): static
    {
        return $this->state(['user_id' => $user->id, 'sort_order' => null]);
    }
}
