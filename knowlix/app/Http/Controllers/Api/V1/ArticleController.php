<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Enums\ArticleStatus;
use App\Services\ArticleService;
use App\Jobs\DeliverWebhookEvent;

class ArticleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected ArticleService $articles) {}

    public function index()
    {
        return ArticleResource::collection(
            Article::with(['category', 'author'])->latest()->paginate(20)
        );
    }

    public function store(StoreArticleRequest $request)
    {
        $article = Article::create([
            ...$request->validated(),
            'author_id' => $request->user()->id,
            'status' => ArticleStatus::Draft,
        ]);

        return new ArticleResource($article->load(['category', 'author']));
    }

    public function show(Article $article)
    {
        $this->authorize('view', $article);

        return new ArticleResource($article->load(['category', 'author']));
    }

    public function update(UpdateArticleRequest $request, Article $article)
    {
        $this->authorize('update', $article);

        $article->update($request->validated());

        return new ArticleResource($article->load(['category', 'author']));
    }

    public function submit(Article $article)
    {
        $this->authorize('submit', $article);

        $article = $this->articles->submitForReview($article);

        return new ArticleResource($article->load(['category', 'author']));
    }


    public function publish(Article $article)
    {
        $this->authorize('publish', $article);

        $result = $this->articles->publish($article, request()->user());

        DeliverWebhookEvent::dispatch($result['event'])->afterCommit();

        return new ArticleResource($result['article']->load(['category', 'author']));
    }



    public function destroy(Article $article)
    {
        $this->authorize('delete', $article);

        $article->delete(); // soft delete, per SoftDeletes

        return response()->json(['message' => 'Article archived'], 200);
    }
}
