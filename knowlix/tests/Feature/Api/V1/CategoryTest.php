<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_create_a_category(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent, 'sanctum')->postJson('/api/v1/categories', ['name' => 'Billing'])
            ->assertStatus(201);

        $this->assertDatabaseHas('categories', ['name' => 'Billing', 'workspace_id' => $agent->workspace_id]);
    }

    public function test_member_cannot_create_a_category(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member, 'sanctum')->postJson('/api/v1/categories', ['name' => 'Billing'])
            ->assertStatus(403);
    }

    public function test_parent_id_must_belong_to_same_workspace(): void
    {
        $agent = User::factory()->agent()->create();
        $otherWorkspaceCategory = Category::factory()->create(); // different workspace

        $this->actingAs($agent, 'sanctum')->postJson('/api/v1/categories', [
            'name' => 'Refunds', 'parent_id' => $otherWorkspaceCategory->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['parent_id']);
    }

    public function test_cannot_delete_category_with_articles(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['workspace_id' => $admin->workspace_id]);
        \App\Models\Article::factory()->create(['workspace_id' => $admin->workspace_id, 'category_id' => $category->id]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertStatus(403);
    }

    public function test_admin_cannot_view_another_workspaces_category(): void
    {
        $adminA = User::factory()->admin()->create();
        $categoryB = Category::factory()->create();

        $this->actingAs($adminA, 'sanctum')
            ->getJson("/api/v1/categories/{$categoryB->id}")
            ->assertStatus(404);
    }
}
