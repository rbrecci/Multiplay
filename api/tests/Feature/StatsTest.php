<?php

namespace Tests\Feature;

use App\Models\Console;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function mark(Game $game, array $pivot, int $minutesAgo): void
    {
        $this->user->games()->attach($game->id, $pivot);
        DB::table('game_user')
            ->where('user_id', $this->user->id)
            ->where('game_id', $game->id)
            ->update(['updated_at' => now()->subMinutes($minutesAgo)]);
    }

    public function test_stats_returns_empty_shape_for_user_without_marks(): void
    {
        $other = User::factory()->create();
        $other->games()->attach(Game::factory()->create()->id, ['status' => 'zerado', 'favorite' => true]);

        $this->getJson('/api/me/stats')->assertOk()->assertExactJson([
            'counts' => ['jogado' => 0, 'interesse' => 0, 'zerado' => 0, 'quero_jogar' => 0],
            'favorites_count' => 0,
            'favorites' => [],
            'recent' => [],
        ]);
    }

    public function test_stats_counts_statuses_and_lists_favorites_and_recent(): void
    {
        $nes = Console::factory()->create(['name' => 'NES']);
        $snes = Console::factory()->create(['name' => 'SNES']);
        $zelda = Game::factory()->create(['console_id' => $nes->id, 'name' => 'Zelda']);
        $mario = Game::factory()->create(['console_id' => $snes->id, 'name' => 'Mario']);
        $contra = Game::factory()->create(['console_id' => $nes->id, 'name' => 'Contra']);
        $onlyNotes = Game::factory()->create(['console_id' => $nes->id, 'name' => 'So notas']);

        $this->mark($zelda, ['status' => 'zerado', 'favorite' => true], 30);
        $this->mark($mario, ['status' => 'zerado', 'favorite' => true], 5);
        $this->mark($contra, ['status' => 'jogado'], 10);
        $this->mark($onlyNotes, ['status' => null, 'notes' => 'x'], 1);

        $response = $this->getJson('/api/me/stats')->assertOk();

        $response->assertJsonPath('counts', ['jogado' => 1, 'interesse' => 0, 'zerado' => 2, 'quero_jogar' => 0])
            ->assertJsonPath('favorites_count', 2)
            ->assertJsonPath('favorites', [
                ['game_id' => $mario->id, 'name' => 'Mario', 'cover_url' => $mario->cover_url, 'console' => ['id' => $snes->id, 'name' => 'SNES']],
                ['game_id' => $zelda->id, 'name' => 'Zelda', 'cover_url' => $zelda->cover_url, 'console' => ['id' => $nes->id, 'name' => 'NES']],
            ])
            ->assertJsonCount(4, 'recent')
            ->assertJsonPath('recent.0.game_id', $onlyNotes->id)
            ->assertJsonPath('recent.0.status', null)
            ->assertJsonPath('recent.1.game_id', $mario->id)
            ->assertJsonPath('recent.2.game_id', $contra->id)
            ->assertJsonPath('recent.3.game_id', $zelda->id)
            ->assertJsonPath('recent.3.status', 'zerado')
            ->assertJsonPath('recent.3.console', ['id' => $nes->id, 'name' => 'NES']);

        $this->assertSame(
            ['game_id', 'name', 'cover_url', 'status', 'console', 'updated_at'],
            array_keys($response->json('recent.0')),
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $response->json('recent.0.updated_at'),
        );
    }

    public function test_stats_limits_favorites_to_12_and_recent_to_10(): void
    {
        $console = Console::factory()->create();
        foreach (range(1, 15) as $i) {
            $game = Game::factory()->create(['console_id' => $console->id]);
            $this->mark($game, ['status' => 'jogado', 'favorite' => true], $i);
        }

        $this->getJson('/api/me/stats')
            ->assertOk()
            ->assertJsonPath('favorites_count', 15)
            ->assertJsonPath('counts.jogado', 15)
            ->assertJsonCount(12, 'favorites')
            ->assertJsonCount(10, 'recent');
    }
}
