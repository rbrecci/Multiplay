<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Saker',
            'email' => 'saker@example.com',
            'password' => 'segredo123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.name', 'Saker')
            ->assertJsonPath('user.email', 'saker@example.com');

        $this->assertDatabaseHas('users', ['email' => 'saker@example.com']);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_register_validates_required_fields_and_unique_email(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->postJson('/api/register', [
            'name' => 'Outro',
            'email' => 'dup@example.com',
            'password' => 'segredo123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'segredo123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'segredo123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.id', $user->id);

        $token = $response->json('token');

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', 'login@example.com');
    }

    public function test_login_with_wrong_password_returns_401_with_message(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'segredo123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'errada',
        ])->assertUnauthorized()->assertJsonStructure(['message']);
    }

    public function test_login_validates_fields(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
    }

    public function test_protected_routes_return_401_without_token(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->getJson('/api/consoles')->assertUnauthorized();
    }

    public function test_protected_routes_return_json_401_even_without_accept_header(): void
    {
        $this->get('/api/me')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('multiplay')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
