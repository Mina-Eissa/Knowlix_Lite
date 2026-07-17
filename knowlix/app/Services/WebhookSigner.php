<?php

namespace App\Services;

class WebhookSigner
{
    public function sign(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), config('services.intelligence.webhook_secret'));
    }
}
