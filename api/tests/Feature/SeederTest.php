<?php

namespace Tests\Feature;

use App\Models\Console;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_demo_user_and_eight_consoles_idempotently(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('consoles', 8);

        $demo = User::where('email', 'demo@multiplay.local')->firstOrFail();
        $this->assertTrue(Hash::check('multiplay', $demo->password));

        $ordered = Console::orderBy('sort_order')->pluck('name')->all();
        $this->assertSame([
            'NES',
            'Super Nintendo',
            'PlayStation',
            'PlayStation 2',
            'Xbox 360',
            'PlayStation 4',
            'Nintendo Switch',
            'PlayStation 5',
        ], $ordered);

        $this->assertDatabaseHas('consoles', [
            'name' => 'NES',
            'manufacturer' => 'Nintendo',
            'release_year' => 1983,
            'sort_order' => 1,
            'igdb_platform_id' => 18,
        ]);
        $this->assertDatabaseHas('consoles', [
            'name' => 'PlayStation 5',
            'manufacturer' => 'Sony',
            'release_year' => 2020,
            'sort_order' => 8,
            'igdb_platform_id' => 167,
        ]);
    }
}
