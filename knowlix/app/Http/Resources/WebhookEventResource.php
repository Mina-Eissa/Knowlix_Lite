<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'type' => $this->type,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'last_error' => $this->last_error,
            'created_at' => $this->created_at,
        ];
    }
}
