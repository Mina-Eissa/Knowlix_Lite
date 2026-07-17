<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'body' => $this->body,
            'editor' => new UserResource($this->whenLoaded('editor')),
            'created_at' => $this->created_at,
        ];
    }
}
