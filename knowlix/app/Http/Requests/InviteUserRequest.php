<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->value === 'admin';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc,dns',
                'max:255',
                Rule::unique('users', 'email')->where('workspace_id', $this->user()->workspace_id),
            ],
            'role' => ['required', 'in:admin,agent,member'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->adminEmailIsValid()) {
                $validator->errors()->add('admin_email', 'Your account email is invalid — contact support.');
                return;
            }

            if (strcasecmp($this->email, $this->user()->email) === 0) {
                $validator->errors()->add('email', 'You cannot invite yourself.');
            }
        });
    }

    protected function adminEmailIsValid(): bool
    {
        $admin = $this->user();

        return $admin && filled($admin->email) && filter_var($admin->email, FILTER_VALIDATE_EMAIL);
    }
}
