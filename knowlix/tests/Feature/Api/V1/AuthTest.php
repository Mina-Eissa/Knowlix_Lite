<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_a_new_workspace_and_admin_user(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'workspace_name' => 'Acme Support',
            'name' => 'Sara',
            'email' => 'sara@acme.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)->assertJsonPath('user.role', 'admin');
        $this->assertDatabaseHas('users', ['email' => 'sara@acme.com', 'role' => 'admin']);
    }

    public function test_register_fails_without_required_fields(): void
    {
        $this->postJson('/api/v1/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['workspace_name', 'name', 'email', 'password']);
    }

    public function test_can_login_with_correct_credentials(): void
    {
        User::factory()->create(['email' => 'sara@acme.com', 'password' => 'password123']);

        $this->postJson('/api/v1/login', ['email' => 'sara@acme.com', 'password' => 'password123'])
            ->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'sara@acme.com', 'password' => 'password123']);

        $this->postJson('/api/v1/login', ['email' => 'sara@acme.com', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me')
            ->assertStatus(200)
            ->assertJsonPath('id', $user->id);
    }
}
