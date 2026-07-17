<?php

namespace Tests\Feature\Api\V1;

use App\Mail\InviteUserMail;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_an_agent(): void
    {
        Mail::fake();

        $workspace = Workspace::factory()->create();
        $admin = User::factory()->admin()->create(['workspace_id' => $workspace->id]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'Jake', 'email' => 'jake@acme.com', 'role' => 'agent',
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', ['email' => 'jake@acme.com', 'workspace_id' => $workspace->id]);
        Mail::assertSent(InviteUserMail::class);
    }

    public function test_agent_cannot_invite_anyone(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'Someone', 'email' => 'someone@acme.com', 'role' => 'member',
        ])->assertStatus(403);
    }

    public function test_admin_cannot_invite_themselves(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'sara@acme.com']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'Sara', 'email' => 'sara@acme.com', 'role' => 'agent',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_cannot_invite_duplicate_email_in_same_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->admin()->create(['workspace_id' => $workspace->id]);
        User::factory()->create(['workspace_id' => $workspace->id, 'email' => 'jake@acme.com']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'Jake', 'email' => 'jake@acme.com', 'role' => 'agent',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_admin_cannot_see_users_from_another_workspace(): void
    {
        $adminA = User::factory()->admin()->create();
        $userB = User::factory()->create();

        $this->actingAs($adminA, 'sanctum')
            ->getJson("/api/v1/users/{$userB->id}")
            ->assertStatus(404);
    }
}
