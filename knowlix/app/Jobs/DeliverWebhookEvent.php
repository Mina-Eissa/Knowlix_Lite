<?php

namespace App\Jobs;

use App\Enums\WebhookEventStatus;
use App\Models\WebhookEvent;
use App\Services\WebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DeliverWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public function __construct(public WebhookEvent $event) {}

    public function backoff(): array
    {
        return [60, 300, 1800, 7200, 21600]; // 1m, 5m, 30m, 2h, 6h
    }

    public function handle(WebhookSigner $signer): void
    {
        $response = Http::withHeaders([
            'X-Knowlix-Signature' => $signer->sign($this->event->payload),
            'X-Knowlix-Event-Id' => $this->event->event_id,
        ])->post(config('services.intelligence.webhook_url'), $this->event->payload);

        if ($response->successful()) {
            $this->event->update([
                'status' => WebhookEventStatus::Delivered,
                'delivered_at' => now(),
            ]);
            return;
        }

        $this->event->increment('attempts');
        throw new \RuntimeException("Webhook delivery failed with status {$response->status()}");
    }

    public function failed(\Throwable $exception): void
    {
        $this->event->update([
            'status' => WebhookEventStatus::Failed,
            'last_error' => $exception->getMessage(),
        ]);
    }
}
