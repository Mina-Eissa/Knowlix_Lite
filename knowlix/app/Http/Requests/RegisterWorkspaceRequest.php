<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterWorkspaceRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 'not_regex:/<[^>]*>/' for avoid XSS attacks, it will reject any input that contains HTML tags
        return [
            'workspace_name' => ['required', 'string', 'max:255','not_regex:/<[^>]*>/'],
            'name' => ['required', 'string', 'max:255','not_regex:/<[^>]*>/'],
            'email' => ['required', 'string', 'email', 'max:255','not_regex:/<[^>]*>/'],
            'password' => ['required', 'string', 'min:8','not_regex:/<[^>]*>/'],
        ];
    }
}
