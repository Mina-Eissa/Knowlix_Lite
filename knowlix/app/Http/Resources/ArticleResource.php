<?php

namespace App\Http\Resources;

use App\Services\MarkdownRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,                                    // raw Markdown, for editing
            'body_html' => app(MarkdownRenderer::class)->toHtml($this->body), // rendered, safe HTML, for display
            'status' => $this->status,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'author' => new UserResource($this->whenLoaded('author')),
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
        ];
    }
}
