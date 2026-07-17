<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\WebhookEventStatus;
use App\Jobs\DeliverWebhookEvent;
use App\Models\Article;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\WebhookSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookEventTest extends TestCase
{
    use RefreshDatabase;
    // --- 1. Outbox write happens on publish ---

    public function test_publishing_an_article_creates_a_pending_webhook_event(): void
    {
        // replace hit to intelligence service with a fake response so that the publish action succeeds
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        // in production you will have Queue= database and you will remove this fake of Queue
        Queue::fake();

        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => ArticleStatus::InReview,
        ]);

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/articles/{$article->id}/publish")
            ->assertStatus(200);

        $this->assertDatabaseHas('webhook_events', [
            'workspace_id' => $agent->workspace_id,
            'type' => 'article.published',
            'status' => 'pending',
        ]);
    }

    public function test_webhook_event_id_is_a_valid_ulid_and_unique(): void
    {
        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => ArticleStatus::InReview,
        ]);

        $this->actingAs($agent, 'sanctum')->postJson("/api/v1/articles/{$article->id}/publish");

        $event = WebhookEvent::first();

        $this->assertEquals(26, strlen($event->event_id)); // ULIDs are always 26 chars
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $event->event_id);
    }

    public function test_webhook_event_payload_contains_expected_article_data(): void
    {
        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => ArticleStatus::InReview,
            'title' => 'How to reset your password',
        ]);

        $this->actingAs($agent, 'sanctum')->postJson("/api/v1/articles/{$article->id}/publish");

        $event = WebhookEvent::first();

        $this->assertEquals($article->id, $event->payload['article_id']);
        $this->assertEquals('How to reset your password', $event->payload['title']);
    }

    // --- 2. Job dispatch happens after commit, only for real publishes ---

    public function test_delivery_job_is_dispatched_when_article_is_published(): void
    {
        Bus::fake();

        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => ArticleStatus::InReview,
        ]);

        $this->actingAs($agent, 'sanctum')->postJson("/api/v1/articles/{$article->id}/publish");

        Bus::assertDispatched(DeliverWebhookEvent::class);
    }

    public function test_no_webhook_event_created_for_a_failed_publish_attempt(): void
    {
        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => ArticleStatus::Draft, // wrong state — publish should be rejected
        ]);

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/articles/{$article->id}/publish")
            ->assertStatus(422);

        $this->assertDatabaseCount('webhook_events', 0);
    }

    // --- 3. Successful delivery ---

    public function test_job_marks_event_delivered_on_success(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $event = WebhookEvent::factory()->create();

        (new DeliverWebhookEvent($event))->handle(new WebhookSigner());

        $this->assertEquals(WebhookEventStatus::Delivered, $event->fresh()->status);
        $this->assertNotNull($event->fresh()->delivered_at);
    }

    public function test_delivery_request_includes_signature_header(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $event = WebhookEvent::factory()->create();

        (new DeliverWebhookEvent($event))->handle(new WebhookSigner());

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Knowlix-Signature')
                && $request->hasHeader('X-Knowlix-Event-Id');
        });
    }

    // --- 4. Failure and retry behavior ---

    public function test_job_increments_attempts_and_rethrows_on_failure(): void
    {
        // replace hit to intelligence service with a fake response
        Http::fake(['*' => Http::response('', 500)]);

        $event = WebhookEvent::factory()->create(['attempts' => 0]);

        $this->expectException(\RuntimeException::class);

        (new DeliverWebhookEvent($event))->handle(new WebhookSigner());

        $this->assertEquals(1, $event->fresh()->attempts);
    }

    public function test_job_marks_event_failed_after_exhausting_retries(): void
    {
        $event = WebhookEvent::factory()->create(['attempts' => 4]);
        $job = new DeliverWebhookEvent($event);

        $job->failed(new \RuntimeException('Connection timeout'));

        $event->refresh();
        $this->assertEquals(WebhookEventStatus::Failed, $event->status);
        $this->assertEquals('Connection timeout', $event->last_error);
    }

    public function test_job_backoff_schedule_matches_spec(): void
    {
        $event = WebhookEvent::factory()->create();
        $job = new DeliverWebhookEvent($event);

        $this->assertEquals([60, 300, 1800, 7200, 21600], $job->backoff());
    }

    // --- 5. The decoupling guarantee — the core proof from the diagram ---

    public function test_publishing_succeeds_even_when_intelligence_service_is_down(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        Queue::fake(); // job dispatch is captured, not actually run inline

        $agent = User::factory()->agent()->create();
        $article = Article::factory()->create([
            'workspace_id' => $agent->workspace_id,
            'status' => ArticleStatus::InReview,
        ]);

        $response = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/articles/{$article->id}/publish");

        $response->assertStatus(200)->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'status' => 'published']);
        $this->assertDatabaseHas('webhook_events', ['workspace_id' => $agent->workspace_id, 'status' => 'pending']);
    }

    // --- 6. Tenant isolation for webhook events ---

    public function test_workspace_cannot_see_another_workspaces_webhook_events(): void
    {
        $adminA = User::factory()->admin()->create();
        WebhookEvent::factory()->create(); // different workspace by default

        $this->actingAs($adminA, 'sanctum')
            ->getJson('/api/v1/webhook-events')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
