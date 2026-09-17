<?php

namespace Database\Factories;

use App\Models\Console;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        return [
            'console_id' => Console::factory(),
            'igdb_id' => fake()->unique()->numberBetween(1, 1000000),
            'name' => fake()->unique()->words(3, true),
            'cover_url' => 'https://images.igdb.com/igdb/image/upload/t_cover_big/'.fake()->lexify('??????').'.jpg',
            'first_release_year' => fake()->numberBetween(1983, 2024),
        ];
    }
}
