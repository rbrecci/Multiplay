<?php

namespace Tests\Feature;

use App\Models\Console;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_import_creates_game_and_returns_201(): void
    {
        $console = Console::factory()->create();

        $response = $this->postJson("/api/consoles/{$console->id}/games", [
            'igdb_id' => 1022,
            'name' => 'Super Mario Bros.',
            'cover_url' => 'https://images.igdb.com/igdb/image/upload/t_cover_big/co1abc.jpg',
            'first_release_year' => 1985,
        ]);

        $response->assertCreated()->assertExactJson([
            'id' => $response->json('id'),
            'igdb_id' => 1022,
            'name' => 'Super Mario Bros.',
            'cover_url' => 'https://images.igdb.com/igdb/image/upload/t_cover_big/co1abc.jpg',
            'first_release_year' => 1985,
            'status' => null,
        ]);

        $this->assertDatabaseHas('games', [
            'console_id' => $console->id,
            'igdb_id' => 1022,
            'name' => 'Super Mario Bros.',
        ]);
    }

    public function test_import_accepts_missing_optional_fields(): void
    {
        $console = Console::factory()->create();

        $this->postJson("/api/consoles/{$console->id}/games", [
            'igdb_id' => 55,
            'name' => 'Sem capa',
        ])->assertCreated()
            ->assertJsonPath('cover_url', null)
            ->assertJsonPath('first_release_year', null);
    }

    public function test_import_is_idempotent_and_returns_200_with_existing_game(): void
    {
        $console = Console::factory()->create();
        $payload = ['igdb_id' => 1022, 'name' => 'Super Mario Bros.'];

        $first = $this->postJson("/api/consoles/{$console->id}/games", $payload);
        $first->assertCreated();

        $this->user->games()->attach($first->json('id'), ['status' => 'zerado']);

        $second = $this->postJson("/api/consoles/{$console->id}/games", $payload + ['name' => 'Nome diferente']);
        $second->assertOk()
            ->assertJsonPath('id', $first->json('id'))
            ->assertJsonPath('name', 'Super Mario Bros.')
            ->assertJsonPath('status', 'zerado');

        $this->assertDatabaseCount('games', 1);
    }

    public function test_same_igdb_id_can_exist_in_different_consoles(): void
    {
        $nes = Console::factory()->create();
        $snes = Console::factory()->create();
        $payload = ['igdb_id' => 1022, 'name' => 'Multi'];

        $this->postJson("/api/consoles/{$nes->id}/games", $payload)->assertCreated();
        $this->postJson("/api/consoles/{$snes->id}/games", $payload)->assertCreated();

        $this->assertDatabaseCount('games', 2);
    }

    public function test_import_validates_payload(): void
    {
        $console = Console::factory()->create();

        $this->postJson("/api/consoles/{$console->id}/games", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['igdb_id', 'name']);

        $this->postJson("/api/consoles/{$console->id}/games", [
            'igdb_id' => 'abc',
            'name' => 'X',
            'cover_url' => 'nao-e-url',
            'first_release_year' => 'ano',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['igdb_id', 'cover_url', 'first_release_year']);
    }

    public function test_status_sets_changes_and_removes_user_mark(): void
    {
        $game = Game::factory()->create();

        $this->postJson("/api/games/{$game->id}/status", ['status' => 'interesse'])
            ->assertOk()
            ->assertExactJson(['game_id' => $game->id, 'status' => 'interesse']);
        $this->assertDatabaseHas('game_user', [
            'user_id' => $this->user->id,
            'game_id' => $game->id,
            'status' => 'interesse',
        ]);

        $this->postJson("/api/games/{$game->id}/status", ['status' => 'zerado'])
            ->assertOk()
            ->assertExactJson(['game_id' => $game->id, 'status' => 'zerado']);
        $this->assertDatabaseCount('game_user', 1);
        $this->assertDatabaseHas('game_user', ['game_id' => $game->id, 'status' => 'zerado']);

        $this->postJson("/api/games/{$game->id}/status", ['status' => null])
            ->assertOk()
            ->assertExactJson(['game_id' => $game->id, 'status' => null]);
        $this->assertDatabaseCount('game_user', 0);
    }

    public function test_status_is_per_user(): void
    {
        $game = Game::factory()->create();
        $other = User::factory()->create();
        $other->games()->attach($game->id, ['status' => 'jogado']);

        $this->postJson("/api/games/{$game->id}/status", ['status' => 'quero_jogar'])->assertOk();

        $this->assertDatabaseCount('game_user', 2);
        $this->assertDatabaseHas('game_user', ['user_id' => $other->id, 'status' => 'jogado']);
        $this->assertDatabaseHas('game_user', ['user_id' => $this->user->id, 'status' => 'quero_jogar']);
    }

    public function test_status_rejects_unknown_value(): void
    {
        $game = Game::factory()->create();

        $this->postJson("/api/games/{$game->id}/status", ['status' => 'abandonado'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->postJson("/api/games/{$game->id}/status", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_status_returns_404_for_unknown_game(): void
    {
        $this->postJson('/api/games/999/status', ['status' => 'jogado'])->assertNotFound();
    }
}
