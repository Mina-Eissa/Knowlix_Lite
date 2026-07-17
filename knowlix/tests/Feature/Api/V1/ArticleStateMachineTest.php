<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArticleStateMachineTest extends TestCase
{
    use RefreshDatabase;

     protected function setUp(): void
    {
        parent::setUp();

        // publish() dispatches a webhook delivery job; with QUEUE_CONNECTION=sync
        // that job runs inline during the request and would otherwise attempt
        // a real network call. Fake by default so these tests never depend on
        // network access — this file isn't testing webhook delivery itself,
        // just the article status transitions.
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    public function test_agent_can_submit_a_draft_article_for_review(): void
    {
        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => ArticleStatus::Draft,
        ]);

        $response = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/articles/{$article->id}/submit");

        $response->assertStatus(200)->assertJsonPath('data.status', 'in_review');
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'status' => 'in_review']);
    }

    public function test_cannot_submit_an_article_that_is_not_a_draft(): void
    {
        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => ArticleStatus::InReview,
        ]);

        $response = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/articles/{$article->id}/submit");

        $response->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_member_cannot_submit_an_article(): void
    {
        /** @var User $member */
        $member = User::factory()->create(); // default role is Member
        $article = Article::factory()->create([
            'workspace_id' => $member->workspace_id,
            'status' => ArticleStatus::Draft,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/articles/{$article->id}/submit")
            ->assertStatus(403);
    }

    public function test_agent_can_publish_an_article_in_review(): void
    {
        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => ArticleStatus::InReview,
        ]);

        $response = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/articles/{$article->id}/publish");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_at', fn ($value) => $value !== null);

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'status' => 'published']);
    }

    public function test_cannot_publish_a_draft_article_directly(): void
    {
        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => ArticleStatus::Draft,
        ]);

        $response = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/articles/{$article->id}/publish");

        $response->assertStatus(422)->assertJsonValidationErrors(['status']);
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'status' => 'draft']);
    }

    public function test_cannot_publish_an_already_published_article(): void
    {
        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => ArticleStatus::Published,
        ]);

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/articles/{$article->id}/publish")
            ->assertStatus(422);
    }

    public function test_member_cannot_publish_an_article(): void
    {
        /** @var User $member */
        $member = User::factory()->create();
        $article = Article::factory()->create([
            'workspace_id' => $member->workspace_id,
            'status' => ArticleStatus::InReview,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/articles/{$article->id}/publish")
            ->assertStatus(403);
    }

    public function test_full_lifecycle_draft_to_review_to_published(): void
    {
        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => ArticleStatus::Draft,
        ]);

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/articles/{$article->id}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'in_review');

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/articles/{$article->id}/publish")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'published');
    }

    public function test_admin_cannot_transition_another_workspaces_article(): void
    {
        $adminA = User::factory()->admin()->create();
        $articleB = Article::factory()->create(['status' => ArticleStatus::Draft]); // different workspace

        $this->actingAs($adminA, 'sanctum')
            ->postJson("/api/v1/articles/{$articleB->id}/submit")
            ->assertStatus(404);
    }
}
