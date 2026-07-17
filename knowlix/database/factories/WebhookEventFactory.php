<?php

namespace Database\Factories;

use App\Enums\WebhookEventStatus;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WebhookEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'event_id' => (string) Str::ulid(),
            'type' => 'article.published',
            'payload' => ['article_id' => 1, 'title' => 'Test article'],
            'status' => WebhookEventStatus::Pending,
            'attempts' => 0,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn () => ['status' => WebhookEventStatus::Delivered, 'delivered_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => WebhookEventStatus::Failed, 'attempts' => 5, 'last_error' => 'Connection timeout']);
    }
}
