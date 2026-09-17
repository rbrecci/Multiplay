<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IgdbPlatformSearchTest extends TestCase
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

    private function fakeIgdb(array $platforms, int $status = 200): void
    {
        Http::fake([
            'https://id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token-fake', 'expires_in' => 5000000]),
            'https://api.igdb.com/v4/platforms' => Http::response($platforms, $status),
        ]);
    }

    public function test_search_returns_platforms_from_igdb(): void
    {
        $this->configureIgdb();
        $this->fakeIgdb([
            ['id' => 29, 'name' => 'Sega Mega Drive/Genesis', 'abbreviation' => 'Genesis'],
            ['id' => 30, 'name' => 'Sega 32X'],
        ]);

        $this->getJson('/api/igdb/platforms/search?q=sega')
            ->assertOk()
            ->assertExactJson([
                ['id' => 29, 'name' => 'Sega Mega Drive/Genesis', 'abbreviation' => 'Genesis'],
                ['id' => 30, 'name' => 'Sega 32X', 'abbreviation' => null],
            ]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.igdb.com/v4/platforms'
            && str_contains($request->body(), 'search "sega";'));
    }

    public function test_search_requires_q_with_min_two_chars(): void
    {
        $this->configureIgdb();
        Http::fake();

        $this->getJson('/api/igdb/platforms/search')->assertUnprocessable()->assertJsonValidationErrors(['q']);
        $this->getJson('/api/igdb/platforms/search?q=s')->assertUnprocessable()->assertJsonValidationErrors(['q']);

        Http::assertNothingSent();
    }

    public function test_search_returns_503_when_igdb_is_not_configured(): void
    {
        config(['services.igdb.client_id' => null, 'services.igdb.client_secret' => null]);
        Http::fake();

        $this->getJson('/api/igdb/platforms/search?q=sega')->assertStatus(503)->assertJsonStructure(['message']);

        Http::assertNothingSent();
    }

    public function test_search_returns_502_when_igdb_fails(): void
    {
        $this->configureIgdb();
        $this->fakeIgdb(['message' => 'boom'], 500);

        $this->getJson('/api/igdb/platforms/search?q=sega')->assertStatus(502)->assertJsonStructure(['message']);
    }
}
