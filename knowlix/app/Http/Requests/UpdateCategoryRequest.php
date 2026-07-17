<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role->value, ['admin', 'agent']);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('workspace_id', $this->user()->workspace_id),
                Rule::notIn([$this->route('category')?->id]),
            ],
            'slug' => ['required', 'string'],
        ];
    }
}
