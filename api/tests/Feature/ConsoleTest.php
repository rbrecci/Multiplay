<?php

namespace Tests\Feature;

use App\Models\Console;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_consoles_ordered_by_sort_order_with_games_count(): void
    {
        $ps2 = Console::factory()->create(['name' => 'PlayStation 2', 'sort_order' => 4]);
        $nes = Console::factory()->create(['name' => 'NES', 'sort_order' => 1]);
        Game::factory()->count(3)->create(['console_id' => $ps2->id]);
        $nesGame = Game::factory()->create(['console_id' => $nes->id]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/consoles');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'NES')
            ->assertJsonPath('0.games_count', 1)
            ->assertJsonPath('1.name', 'PlayStation 2')
            ->assertJsonPath('1.games_count', 3)
            ->assertJsonPath('0.user_id', null)
            ->assertJsonPath('0.completed_count', 0)
            ->assertJsonPath('0.covers', [$nesGame->cover_url]);

        $this->assertSame(
            ['id', 'name', 'manufacturer', 'release_year', 'sort_order', 'igdb_platform_id', 'user_id', 'games_count', 'completed_count', 'covers'],
            array_keys($response->json('0')),
        );
    }

    public function test_games_returns_console_and_games_with_status_of_logged_user(): void
    {
        $console = Console::factory()->create(['name' => 'NES']);
        $zelda = Game::factory()->create(['console_id' => $console->id, 'name' => 'Zelda']);
        $mario = Game::factory()->create(['console_id' => $console->id, 'name' => 'Mario']);
        $contra = Game::factory()->create(['console_id' => $console->id, 'name' => 'Contra']);
        Game::factory()->create(['name' => 'Jogo de outro console']);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $alice->games()->attach($zelda->id, ['status' => 'zerado']);
        $alice->games()->attach($mario->id, ['status' => 'interesse']);
        $bob->games()->attach($zelda->id, ['status' => 'jogado']);

        Sanctum::actingAs($alice);
        $response = $this->getJson("/api/consoles/{$console->id}/games");

        $response->assertOk()
            ->assertJsonPath('console.id', $console->id)
            ->assertJsonPath('console.name', 'NES')
            ->assertJsonStructure([
                'console' => ['id', 'name', 'manufacturer', 'release_year', 'igdb_platform_id', 'user_id'],
                'games' => ['*' => ['id', 'igdb_id', 'name', 'cover_url', 'first_release_year', 'status']],
            ])
            ->assertJsonCount(3, 'games')
            ->assertJsonPath('games.0.name', 'Contra')
            ->assertJsonPath('games.0.status', null)
            ->assertJsonPath('games.1.name', 'Mario')
            ->assertJsonPath('games.1.status', 'interesse')
            ->assertJsonPath('games.2.name', 'Zelda')
            ->assertJsonPath('games.2.status', 'zerado');

        Sanctum::actingAs($bob);
        $this->getJson("/api/consoles/{$console->id}/games")
            ->assertOk()
            ->assertJsonPath('games.1.status', null)
            ->assertJsonPath('games.2.status', 'jogado');
    }

    public function test_games_returns_404_for_unknown_console(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/consoles/999/games')->assertNotFound();
    }
}
