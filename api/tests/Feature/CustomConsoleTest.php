<?php

namespace Tests\Feature;

use App\Models\Console;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomConsoleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_index_lists_globals_then_own_custom_consoles_in_order(): void
    {
        $other = User::factory()->create();
        Console::factory()->create(['name' => 'Global B', 'sort_order' => 2]);
        Console::factory()->create(['name' => 'Global A', 'sort_order' => 1]);
        Console::factory()->ownedBy($this->user)->create(['name' => 'Meu Z', 'release_year' => 1990]);
        Console::factory()->ownedBy($this->user)->create(['name' => 'Meu B', 'release_year' => 2005]);
        Console::factory()->ownedBy($this->user)->create(['name' => 'Meu A', 'release_year' => 2005]);
        Console::factory()->ownedBy($other)->create(['name' => 'Do outro', 'release_year' => 1980]);

        $response = $this->getJson('/api/consoles')->assertOk()->assertJsonCount(5);

        $this->assertSame(
            ['Global A', 'Global B', 'Meu Z', 'Meu A', 'Meu B'],
            array_column($response->json(), 'name'),
        );
        $response->assertJsonPath('2.user_id', $this->user->id)
            ->assertJsonPath('2.sort_order', null);
    }

    public function test_index_counts_completed_games_and_lists_latest_four_covers(): void
    {
        $console = Console::factory()->create();
        $other = User::factory()->create();

        $games = collect(range(1, 6))->map(fn (int $i) => Game::factory()->create([
            'console_id' => $console->id,
            'cover_url' => "https://img.test/c{$i}.jpg",
        ]));
        $noCover = Game::factory()->create(['console_id' => $console->id, 'cover_url' => null]);

        foreach ($games as $i => $game) {
            DB::table('games')->where('id', $game->id)->update(['created_at' => now()->subDays(10 - $i)]);
        }
        DB::table('games')->where('id', $noCover->id)->update(['created_at' => now()]);

        $this->user->games()->attach($games[0]->id, ['status' => 'zerado']);
        $this->user->games()->attach($games[1]->id, ['status' => 'zerado']);
        $this->user->games()->attach($games[2]->id, ['status' => 'jogado']);
        $other->games()->attach($games[3]->id, ['status' => 'zerado']);

        $this->getJson('/api/consoles')
            ->assertOk()
            ->assertJsonPath('0.games_count', 7)
            ->assertJsonPath('0.completed_count', 2)
            ->assertJsonPath('0.covers', [
                'https://img.test/c6.jpg',
                'https://img.test/c5.jpg',
                'https://img.test/c4.jpg',
                'https://img.test/c3.jpg',
            ]);
    }

    public function test_store_creates_custom_console_for_logged_user(): void
    {
        $response = $this->postJson('/api/consoles', [
            'name' => 'Mega Drive',
            'manufacturer' => 'Sega',
            'release_year' => 1988,
            'igdb_platform_id' => 29,
        ]);

        $response->assertCreated()->assertExactJson([
            'id' => $response->json('id'),
            'name' => 'Mega Drive',
            'manufacturer' => 'Sega',
            'release_year' => 1988,
            'sort_order' => null,
            'igdb_platform_id' => 29,
            'user_id' => $this->user->id,
            'games_count' => 0,
            'completed_count' => 0,
            'covers' => [],
        ]);

        $this->assertDatabaseHas('consoles', [
            'name' => 'Mega Drive',
            'user_id' => $this->user->id,
            'sort_order' => null,
        ]);
    }

    public function test_store_accepts_null_platform_and_validates_payload(): void
    {
        $this->postJson('/api/consoles', [
            'name' => 'Sem IGDB',
            'manufacturer' => 'Caseiro',
            'release_year' => 2001,
        ])->assertCreated()->assertJsonPath('igdb_platform_id', null);

        $this->postJson('/api/consoles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'manufacturer', 'release_year']);

        $this->postJson('/api/consoles', [
            'name' => str_repeat('a', 101),
            'manufacturer' => str_repeat('b', 61),
            'release_year' => 1969,
            'igdb_platform_id' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'manufacturer', 'release_year', 'igdb_platform_id']);
    }

    public function test_console_routes_return_404_for_custom_console_of_another_user(): void
    {
        $other = User::factory()->create();
        $foreign = Console::factory()->ownedBy($other)->create(['igdb_platform_id' => 18]);
        $own = Console::factory()->ownedBy($this->user)->create(['igdb_platform_id' => 18]);
        $payload = ['igdb_id' => 1, 'name' => 'Jogo'];

        $this->getJson("/api/consoles/{$foreign->id}/games")->assertNotFound();
        $this->getJson("/api/consoles/{$foreign->id}/igdb/search?q=mario")->assertNotFound();
        $this->postJson("/api/consoles/{$foreign->id}/games", $payload)->assertNotFound();
        $this->postJson("/api/consoles/{$foreign->id}/games/bulk", ['games' => [$payload]])->assertNotFound();

        $this->getJson("/api/consoles/{$own->id}/games")
            ->assertOk()
            ->assertJsonPath('console.user_id', $this->user->id);
        $this->postJson("/api/consoles/{$own->id}/games", $payload)->assertCreated();
        $this->assertDatabaseCount('games', 1);
    }

    public function test_deleting_user_removes_custom_consoles(): void
    {
        $console = Console::factory()->ownedBy($this->user)->create();
        Console::factory()->create();

        $this->user->delete();

        $this->assertDatabaseMissing('consoles', ['id' => $console->id]);
        $this->assertDatabaseCount('consoles', 1);
    }
}
