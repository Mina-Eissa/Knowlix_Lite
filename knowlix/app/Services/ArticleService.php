<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use App\Models\ArticleVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\WebhookEvent;
use App\Enums\WebhookEventStatus;
use Illuminate\Support\Str;

class ArticleService
{
    public function create(array $data, User $author): Article
    {
        return Article::create([
            ...$data,
            'author_id' => $author->id,
            'status' => ArticleStatus::Draft,
        ]);
    }

    public function update(Article $article, array $data): Article
    {
        $article->update($data);

        return $article;
    }

    public function submitForReview(Article $article): Article
    {
        if ($article->status !== ArticleStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft articles can be submitted for review.',
            ]);
        }

        $article->update(['status' => ArticleStatus::InReview]);

        return $article;
    }

    public function publish(Article $article, User $editor): array
    {
        if ($article->status !== ArticleStatus::InReview) {
            throw ValidationException::withMessages([
                'status' => 'Only articles in review can be published.',
            ]);
        }

        return DB::transaction(function () use ($article, $editor) {
            $article->update([
                'status' => ArticleStatus::Published,
                'published_at' => now(),
            ]);

            $nextVersion = $article->versions()->max('version') + 1;

            ArticleVersion::create([
                'article_id' => $article->id,
                'version' => $nextVersion,
                'body' => $article->body,
                'editor_id' => $editor->id,
            ]);

            $event = WebhookEvent::create([
                'workspace_id' => $article->workspace_id,
                'event_id' => (string) Str::ulid(),
                'type' => 'article.published',
                'payload' => [
                    'article_id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'published_at' => $article->published_at->toIso8601String(),
                ],
                'status' => WebhookEventStatus::Pending,
            ]);

            return ['article' => $article, 'event' => $event];
        });
    }
}
