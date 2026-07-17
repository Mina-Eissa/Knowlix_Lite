<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 'not_regex:/<[^>]*>/' for avoid XSS attacks, it will reject any input that contains HTML tags
        return [
            'email' => ['required', 'email','not_regex:/<[^>]*>/'],
            'password' => ['required', 'string','not_regex:/<[^>]*>/'],
        ];
    }
}
