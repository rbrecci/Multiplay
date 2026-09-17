<?php

namespace Tests\Feature;

use App\Models\Console;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameBulkImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_bulk_imports_new_games_and_reuses_existing_ones_with_user_status(): void
    {
        $console = Console::factory()->create();
        $existing = Game::factory()->create(['console_id' => $console->id, 'igdb_id' => 1022, 'name' => 'Super Mario Bros.']);
        $this->user->games()->attach($existing->id, ['status' => 'zerado']);
        Game::factory()->create(['igdb_id' => 55]);

        $response = $this->postJson("/api/consoles/{$console->id}/games/bulk", [
            'games' => [
                ['igdb_id' => 1022, 'name' => 'Nome diferente'],
                [
                    'igdb_id' => 55,
                    'name' => 'Zelda',
                    'cover_url' => 'https://images.igdb.com/igdb/image/upload/t_cover_big/co2.jpg',
                    'first_release_year' => 1986,
                ],
                ['igdb_id' => 77, 'name' => 'Sem extras'],
            ],
        ]);

        $response->assertOk()->assertExactJson([
            'games' => [
                [
                    'id' => $existing->id,
                    'igdb_id' => 1022,
                    'name' => 'Super Mario Bros.',
                    'cover_url' => $existing->cover_url,
                    'first_release_year' => $existing->first_release_year,
                    'status' => 'zerado',
                ],
                [
                    'id' => $response->json('games.1.id'),
                    'igdb_id' => 55,
                    'name' => 'Zelda',
                    'cover_url' => 'https://images.igdb.com/igdb/image/upload/t_cover_big/co2.jpg',
                    'first_release_year' => 1986,
                    'status' => null,
                ],
                [
                    'id' => $response->json('games.2.id'),
                    'igdb_id' => 77,
                    'name' => 'Sem extras',
                    'cover_url' => null,
                    'first_release_year' => null,
                    'status' => null,
                ],
            ],
        ]);

        $this->assertDatabaseCount('games', 4);
        $this->assertDatabaseHas('games', ['console_id' => $console->id, 'igdb_id' => 55, 'name' => 'Zelda']);

        $this->postJson("/api/consoles/{$console->id}/games/bulk", [
            'games' => [['igdb_id' => 55, 'name' => 'Zelda'], ['igdb_id' => 77, 'name' => 'Sem extras']],
        ])->assertOk()->assertJsonCount(2, 'games');
        $this->assertDatabaseCount('games', 4);
    }

    public function test_bulk_validates_array_size_and_each_item(): void
    {
        $console = Console::factory()->create();

        $this->postJson("/api/consoles/{$console->id}/games/bulk", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['games']);

        $this->postJson("/api/consoles/{$console->id}/games/bulk", ['games' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['games']);

        $tooMany = array_map(fn (int $i) => ['igdb_id' => $i, 'name' => "Jogo {$i}"], range(1, 51));
        $this->postJson("/api/consoles/{$console->id}/games/bulk", ['games' => $tooMany])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['games']);

        $this->postJson("/api/consoles/{$console->id}/games/bulk", [
            'games' => [
                ['igdb_id' => 1, 'name' => 'Ok'],
                ['igdb_id' => 'abc', 'cover_url' => 'nao-e-url', 'first_release_year' => 'ano'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'games.1.igdb_id',
                'games.1.name',
                'games.1.cover_url',
                'games.1.first_release_year',
            ]);

        $this->assertDatabaseCount('games', 0);
    }

    public function test_bulk_returns_404_for_unknown_console(): void
    {
        $this->postJson('/api/consoles/999/games/bulk', ['games' => [['igdb_id' => 1, 'name' => 'X']]])
            ->assertNotFound();
    }
}
