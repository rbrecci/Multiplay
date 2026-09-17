<?php

namespace Tests\Feature;

use App\Models\Console;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IgdbSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Sanctum::actingAs(User::factory()->create());
    }

    private function configureIgdb(): void
    {
        config([
            'services.igdb.client_id' => 'client-id-teste',
            'services.igdb.client_secret' => 'client-secret-teste',
        ]);
    }

    private function fakeIgdb(array $games, int $gamesStatus = 200): void
    {
        Http::fake([
            'https://id.twitch.tv/oauth2/token' => Http::response([
                'access_token' => 'token-fake',
                'expires_in' => 5000000,
                'token_type' => 'bearer',
            ]),
            'https://api.igdb.com/v4/games' => Http::response($games, $gamesStatus),
        ]);
    }

    public function test_search_normalizes_igdb_results_and_resolves_game_id(): void
    {
        $this->configureIgdb();
        $console = Console::factory()->create(['igdb_platform_id' => 18]);
        $imported = Game::factory()->create(['console_id' => $console->id, 'igdb_id' => 1022]);
        Game::factory()->create(['igdb_id' => 1022]);

        $this->fakeIgdb([
            [
                'id' => 1022,
                'name' => 'Super Mario Bros.',
                'cover' => ['id' => 1, 'url' => '//images.igdb.com/igdb/image/upload/t_thumb/co1abc.jpg'],
                'first_release_date' => 495072000,
            ],
            [
                'id' => 2000,
                'name' => 'Jogo sem capa nem data',
            ],
        ]);

        $response = $this->getJson("/api/consoles/{$console->id}/igdb/search?q=mario");

        $response->assertOk()->assertExactJson([
            [
                'igdb_id' => 1022,
                'name' => 'Super Mario Bros.',
                'cover_url' => 'https://images.igdb.com/igdb/image/upload/t_cover_big/co1abc.jpg',
                'first_release_year' => 1985,
                'game_id' => $imported->id,
            ],
            [
                'igdb_id' => 2000,
                'name' => 'Jogo sem capa nem data',
                'cover_url' => null,
                'first_release_year' => null,
                'game_id' => null,
            ],
        ]);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://id.twitch.tv/oauth2/token'
                && $request['client_id'] === 'client-id-teste'
                && $request['client_secret'] === 'client-secret-teste'
                && $request['grant_type'] === 'client_credentials';
        });

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.igdb.com/v4/games'
                && $request->hasHeader('Client-ID', 'client-id-teste')
                && $request->hasHeader('Authorization', 'Bearer token-fake')
                && str_contains($request->body(), 'search "mario";')
                && str_contains($request->body(), 'where platforms = (18);');
        });
    }

    public function test_search_escapes_double_quotes_in_query(): void
    {
        $this->configureIgdb();
        $console = Console::factory()->create(['igdb_platform_id' => 18]);
        $this->fakeIgdb([]);

        $this->getJson("/api/consoles/{$console->id}/igdb/search?q=".urlencode('a"b'))->assertOk();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.igdb.com/v4/games'
            && str_contains($request->body(), 'search "a\\"b";'));
    }

    public function test_search_caches_twitch_token_between_calls(): void
    {
        $this->configureIgdb();
        $console = Console::factory()->create(['igdb_platform_id' => 18]);
        $this->fakeIgdb([]);

        $this->getJson("/api/consoles/{$console->id}/igdb/search?q=zelda")->assertOk();
        $this->getJson("/api/consoles/{$console->id}/igdb/search?q=zelda")->assertOk();

        Http::assertSentCount(3);
    }

    public function test_search_requires_q_with_min_two_chars(): void
    {
        $this->configureIgdb();
        $console = Console::factory()->create(['igdb_platform_id' => 18]);
        Http::fake();

        $this->getJson("/api/consoles/{$console->id}/igdb/search")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);

        $this->getJson("/api/consoles/{$console->id}/igdb/search?q=a")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);

        Http::assertNothingSent();
    }

    public function test_search_returns_503_when_igdb_is_not_configured(): void
    {
        config(['services.igdb.client_id' => null, 'services.igdb.client_secret' => null]);
        $console = Console::factory()->create(['igdb_platform_id' => 18]);
        Http::fake();

        $this->getJson("/api/consoles/{$console->id}/igdb/search?q=mario")
            ->assertStatus(503)
            ->assertJsonStructure(['message']);

        Http::assertNothingSent();
    }

    public function test_search_returns_503_when_console_has_no_platform_id(): void
    {
        $this->configureIgdb();
        $console = Console::factory()->create(['igdb_platform_id' => null]);
        Http::fake();

        $this->getJson("/api/consoles/{$console->id}/igdb/search?q=mario")
            ->assertStatus(503)
            ->assertJsonStructure(['message']);

        Http::assertNothingSent();
    }

    public function test_search_returns_502_when_igdb_fails(): void
    {
        $this->configureIgdb();
        $console = Console::factory()->create(['igdb_platform_id' => 18]);
        $this->fakeIgdb(['message' => 'boom'], 500);

        $this->getJson("/api/consoles/{$console->id}/igdb/search?q=mario")
            ->assertStatus(502)
            ->assertJsonStructure(['message']);
    }

    public function test_search_returns_502_when_twitch_token_fails(): void
    {
        $this->configureIgdb();
        $console = Console::factory()->create(['igdb_platform_id' => 18]);
        Http::fake([
            'https://id.twitch.tv/oauth2/token' => Http::response(['message' => 'invalid client'], 400),
        ]);

        $this->getJson("/api/consoles/{$console->id}/igdb/search?q=mario")
            ->assertStatus(502)
            ->assertJsonStructure(['message']);
    }
}
