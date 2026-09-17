<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IgdbPlatformsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_command_prints_platform_table(): void
    {
        config([
            'services.igdb.client_id' => 'client-id-teste',
            'services.igdb.client_secret' => 'client-secret-teste',
        ]);
        Http::fake([
            'https://id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 5000]),
            'https://api.igdb.com/v4/platforms' => Http::response([
                ['id' => 18, 'name' => 'Nintendo Entertainment System', 'abbreviation' => 'NES'],
                ['id' => 19, 'name' => 'Super Nintendo Entertainment System', 'abbreviation' => 'SNES'],
            ]),
        ]);

        $this->artisan('igdb:platforms', ['search' => 'nintendo'])
            ->expectsTable(['id', 'name', 'abbreviation'], [
                [18, 'Nintendo Entertainment System', 'NES'],
                [19, 'Super Nintendo Entertainment System', 'SNES'],
            ])
            ->assertSuccessful();

        Http::assertSent(fn ($request) => $request->url() === 'https://api.igdb.com/v4/platforms'
            && str_contains($request->body(), 'fields id,name,abbreviation; search "nintendo"; limit 20;'));
    }

    public function test_command_fails_gracefully_without_credentials(): void
    {
        config(['services.igdb.client_id' => null, 'services.igdb.client_secret' => null]);
        Http::fake();

        $this->artisan('igdb:platforms', ['search' => 'nintendo'])->assertFailed();

        Http::assertNothingSent();
    }
}
