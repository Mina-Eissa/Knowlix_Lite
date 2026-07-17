<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Rules\SafeHtmlContent;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role->value, ['admin', 'agent']);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', 'not_regex:/<[^>]*>/'],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where('workspace_id', $this->user()->workspace_id),
            ],
            'body' => ['required', 'string', new SafeHtmlContent()],
            'slug' => ['required', 'string', 'max:255', 'unique:articles,slug'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->title) . '-' . Str::random(6),
        ]);
    }
}
