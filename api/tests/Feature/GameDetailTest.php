<?php

namespace Tests\Feature;

use App\Models\Console;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_detail_creates_pivot_row_with_null_status_when_missing(): void
    {
        $game = Game::factory()->create();

        $this->patchJson("/api/games/{$game->id}/detail", ['notes' => 'Muito bom', 'rating' => 4])
            ->assertOk()
            ->assertExactJson([
                'game_id' => $game->id,
                'status' => null,
                'notes' => 'Muito bom',
                'rating' => 4,
                'favorite' => false,
            ]);

        $this->assertDatabaseHas('game_user', [
            'user_id' => $this->user->id,
            'game_id' => $game->id,
            'status' => null,
            'notes' => 'Muito bom',
            'rating' => 4,
            'favorite' => false,
        ]);
    }

    public function test_detail_updates_only_sent_fields_and_keeps_status(): void
    {
        $game = Game::factory()->create();
        $this->user->games()->attach($game->id, ['status' => 'zerado', 'notes' => 'Antiga', 'rating' => 2]);

        $this->patchJson("/api/games/{$game->id}/detail", ['favorite' => true])
            ->assertOk()
            ->assertExactJson([
                'game_id' => $game->id,
                'status' => 'zerado',
                'notes' => 'Antiga',
                'rating' => 2,
                'favorite' => true,
            ]);

        $this->patchJson("/api/games/{$game->id}/detail", ['notes' => null, 'rating' => null])
            ->assertOk()
            ->assertExactJson([
                'game_id' => $game->id,
                'status' => 'zerado',
                'notes' => null,
                'rating' => null,
                'favorite' => true,
            ]);

        $this->assertDatabaseCount('game_user', 1);
    }

    public function test_detail_requires_at_least_one_field_and_validates_ranges(): void
    {
        $game = Game::factory()->create();

        $this->patchJson("/api/games/{$game->id}/detail", [])->assertUnprocessable();

        $this->patchJson("/api/games/{$game->id}/detail", [
            'notes' => str_repeat('a', 2001),
            'rating' => 6,
            'favorite' => 'talvez',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['notes', 'rating', 'favorite']);

        $this->patchJson("/api/games/{$game->id}/detail", ['rating' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);

        $this->assertDatabaseCount('game_user', 0);
    }

    public function test_detail_is_per_user_and_returns_404_for_unknown_game(): void
    {
        $game = Game::factory()->create();
        $other = User::factory()->create();
        $other->games()->attach($game->id, ['status' => 'jogado', 'favorite' => true]);

        $this->patchJson("/api/games/{$game->id}/detail", ['favorite' => false])
            ->assertOk()
            ->assertJsonPath('favorite', false);

        $this->assertDatabaseHas('game_user', ['user_id' => $other->id, 'favorite' => true]);
        $this->assertDatabaseCount('game_user', 2);

        $this->patchJson('/api/games/999/detail', ['favorite' => true])->assertNotFound();
    }

    public function test_status_null_keeps_row_when_notes_or_favorite_exist(): void
    {
        $game = Game::factory()->create();
        $this->user->games()->attach($game->id, ['status' => 'jogado', 'notes' => 'Guardar']);

        $this->postJson("/api/games/{$game->id}/status", ['status' => null])
            ->assertOk()
            ->assertExactJson(['game_id' => $game->id, 'status' => null]);

        $this->assertDatabaseHas('game_user', [
            'user_id' => $this->user->id,
            'game_id' => $game->id,
            'status' => null,
            'notes' => 'Guardar',
        ]);

        $this->patchJson("/api/games/{$game->id}/detail", ['notes' => null, 'favorite' => true])->assertOk();
        $this->postJson("/api/games/{$game->id}/status", ['status' => null])->assertOk();
        $this->assertDatabaseCount('game_user', 1);

        $this->patchJson("/api/games/{$game->id}/detail", ['favorite' => false])->assertOk();
        $this->postJson("/api/games/{$game->id}/status", ['status' => null])->assertOk();
        $this->assertDatabaseCount('game_user', 0);
    }

    public function test_console_games_include_detail_fields_and_created_at(): void
    {
        $console = Console::factory()->create();
        $marked = Game::factory()->create(['console_id' => $console->id, 'name' => 'A']);
        $plain = Game::factory()->create(['console_id' => $console->id, 'name' => 'B']);
        $this->user->games()->attach($marked->id, ['status' => 'zerado', 'notes' => 'Nota', 'rating' => 5, 'favorite' => true]);

        $response = $this->getJson("/api/consoles/{$console->id}/games")->assertOk();

        $response->assertJsonPath('games.0.id', $marked->id)
            ->assertJsonPath('games.0.status', 'zerado')
            ->assertJsonPath('games.0.notes', 'Nota')
            ->assertJsonPath('games.0.rating', 5)
            ->assertJsonPath('games.0.favorite', true)
            ->assertJsonPath('games.0.created_at', $marked->created_at->toIso8601String())
            ->assertJsonPath('games.1.id', $plain->id)
            ->assertJsonPath('games.1.notes', null)
            ->assertJsonPath('games.1.rating', null)
            ->assertJsonPath('games.1.favorite', false);

        $this->assertSame(
            ['id', 'igdb_id', 'name', 'cover_url', 'first_release_year', 'status', 'notes', 'rating', 'favorite', 'created_at'],
            array_keys($response->json('games.0')),
        );
    }
}
