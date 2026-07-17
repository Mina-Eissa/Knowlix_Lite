<?php

namespace App\Http\Requests;

use App\Enums\ArticleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\SafeHtmlContent;

class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return in_array($this->user()->role->value, ['admin', 'agent'])
            && $article->status !== ArticleStatus::Published;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255', 'not_regex:/<[^>]*>/'],
            'category_id' => [
                'sometimes',
                'required',
                Rule::exists('categories', 'id')->where('workspace_id', $this->user()->workspace_id),
            ],
            'body' => ['sometimes', 'required', 'string', new SafeHtmlContent()],
            'slug' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}
