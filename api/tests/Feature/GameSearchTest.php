<?php

namespace Tests\Feature;

use App\Models\Console;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_search_finds_only_games_marked_by_logged_user_across_consoles(): void
    {
        $nes = Console::factory()->create(['name' => 'NES', 'manufacturer' => 'Nintendo']);
        $snes = Console::factory()->create(['name' => 'SNES', 'manufacturer' => 'Nintendo']);
        $mario3 = Game::factory()->create(['console_id' => $nes->id, 'name' => 'Super Mario Bros. 3']);
        $marioWorld = Game::factory()->create(['console_id' => $snes->id, 'name' => 'Super mario World']);
        $marioUnmarked = Game::factory()->create(['console_id' => $nes->id, 'name' => 'Mario Bros.']);
        $zelda = Game::factory()->create(['console_id' => $nes->id, 'name' => 'Zelda']);
        $marioOther = Game::factory()->create(['console_id' => $nes->id, 'name' => 'Dr. Mario']);

        $this->user->games()->attach($marioWorld->id, ['status' => 'zerado', 'favorite' => true]);
        $this->user->games()->attach($mario3->id, ['status' => null, 'notes' => 'so notas']);
        $this->user->games()->attach($zelda->id, ['status' => 'jogado']);
        User::factory()->create()->games()->attach($marioOther->id, ['status' => 'jogado']);

        $this->getJson('/api/games/search?q=mario')->assertOk()->assertExactJson([
            [
                'id' => $mario3->id,
                'name' => 'Super Mario Bros. 3',
                'cover_url' => $mario3->cover_url,
                'first_release_year' => $mario3->first_release_year,
                'status' => null,
                'favorite' => false,
                'console' => ['id' => $nes->id, 'name' => 'NES', 'manufacturer' => 'Nintendo'],
            ],
            [
                'id' => $marioWorld->id,
                'name' => 'Super mario World',
                'cover_url' => $marioWorld->cover_url,
                'first_release_year' => $marioWorld->first_release_year,
                'status' => 'zerado',
                'favorite' => true,
                'console' => ['id' => $snes->id, 'name' => 'SNES', 'manufacturer' => 'Nintendo'],
            ],
        ]);

        $this->assertNotContains($marioUnmarked->id, array_column($this->getJson('/api/games/search?q=mario')->json(), 'id'));
    }

    public function test_search_requires_q_with_min_two_chars(): void
    {
        $this->getJson('/api/games/search')->assertUnprocessable()->assertJsonValidationErrors(['q']);
        $this->getJson('/api/games/search?q=m')->assertUnprocessable()->assertJsonValidationErrors(['q']);
    }

    public function test_search_limits_to_50_results(): void
    {
        $console = Console::factory()->create();
        foreach (range(1, 55) as $i) {
            $game = Game::factory()->create(['console_id' => $console->id, 'name' => sprintf('Jogo %03d', $i)]);
            $this->user->games()->attach($game->id, ['status' => 'interesse']);
        }

        $this->getJson('/api/games/search?q=jogo')->assertOk()->assertJsonCount(50);
    }
}
