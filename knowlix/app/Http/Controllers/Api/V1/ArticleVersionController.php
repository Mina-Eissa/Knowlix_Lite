<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleVersionResource;
use App\Models\Article;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class ArticleVersionController extends Controller
{
    use AuthorizesRequests;

    public function index(Article $article)
    {
        $this->authorize('view', $article);

        return ArticleVersionResource::collection(
            $article->versions()->with('editor')->orderByDesc('version')->get()
        );
    }
}
