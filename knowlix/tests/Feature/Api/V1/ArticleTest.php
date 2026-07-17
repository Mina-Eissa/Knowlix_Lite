<?php

namespace Tests\Feature\Api\V1;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;
    # 1. Test that an agent can create a draft article.
    public function test_agent_can_create_a_draft_article(): void
    {
        $workspace = Workspace::factory()->create();
        $agent = User::factory()->agent()->create(['workspace_id' => $workspace->id]);
        $category = Category::factory()->create(['workspace_id' => $workspace->id]);

        $response = $this->actingAs($agent, 'sanctum')->postJson('/api/v1/articles', [
            'title' => 'How to reset your password',
            'category_id' => $category->id,
            'body' => "## Steps\n\nClick **forgot password**.",
        ]);

        $response->assertStatus(201)->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('articles', ['title' => 'How to reset your password', 'workspace_id' => $workspace->id]);
    }


    # 2. Test that a member cannot create an article.
    public function test_member_cannot_create_an_article(): void
    {
        $member = User::factory()->create(); // default role is Member per UserFactory
        $category = Category::factory()->create(['workspace_id' => $member->workspace_id]);

        $response = $this->actingAs($member, 'sanctum')->postJson('/api/v1/articles', [
            'title' => 'Test',
            'category_id' => $category->id,
            'body' => 'Body text',
        ]);

        $response->assertStatus(403);
    }

    # 3. Test that article creation rejects raw script tags.
    public function test_article_creation_rejects_raw_script_tags(): void
    {
        $agent = User::factory()->agent()->create();
        $category = Category::factory()->create(['workspace_id' => $agent->workspace_id]);

        $response = $this->actingAs($agent, 'sanctum')->postJson('/api/v1/articles', [
            'title' => 'Test',
            'category_id' => $category->id,
            'body' => 'Hello <script>alert(1)</script>',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['body']);
    }

    # 4. Test that article creation rejects javascript links.
    public function test_article_creation_rejects_javascript_links(): void
    {
        $agent = User::factory()->agent()->create();
        $category = Category::factory()->create(['workspace_id' => $agent->workspace_id]);

        $response = $this->actingAs($agent, 'sanctum')->postJson('/api/v1/articles', [
            'title' => 'Test',
            'category_id' => $category->id,
            'body' => '[Click here](javascript:alert(1))',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['body']);
    }

    # 5. Test that the category_id must belong to the same workspace as the agent creating the article.
    public function test_category_id_must_belong_to_the_same_workspace(): void
    {
        $agent = User::factory()->agent()->create();
        $otherWorkspaceCategory = Category::factory()->create(); // different workspace by default

        $response = $this->actingAs($agent, 'sanctum')->postJson('/api/v1/articles', [
            'title' => 'Test',
            'category_id' => $otherWorkspaceCategory->id,
            'body' => 'Body text',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['category_id']);
    }

    # 6. Test that an agent cannot edit a published article through the update endpoint.
    public function test_cannot_edit_a_published_article_through_the_update_endpoint(): void
    {
        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => 'published',
        ]);

        $response = $this->actingAs($agent, 'sanctum')->putJson("/api/v1/articles/{$article->id}", [
            'title' => 'Changed title',
        ]);

        $response->assertStatus(403);
    }
    # 7. Test that only an admin can delete/archive an article.
    public function test_only_admin_can_delete_archive_an_article(): void
    {
        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create(['workspace_id' => $agent->workspace_id]);

        $this->actingAs($agent, 'sanctum')
            ->deleteJson("/api/v1/articles/{$article->id}")
            ->assertStatus(403);

        $admin = User::factory()->admin()->create(['workspace_id' => $agent->workspace_id]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/articles/{$article->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('articles', ['id' => $article->id]);
    }

    # 8. Test that an admin cannot view an article from another workspace.
    public function test_admin_cannot_view_another_workspaces_article(): void
    {
        $adminA = User::factory()->admin()->create();
        $articleB = Article::factory()->create(); // different workspace by default

        $this->actingAs($adminA, 'sanctum')
            ->getJson("/api/v1/articles/{$articleB->id}")
            ->assertStatus(404);
    }
}
