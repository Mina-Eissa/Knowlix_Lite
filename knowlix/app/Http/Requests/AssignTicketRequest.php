<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'assignee_id' => [
                'required',
                Rule::exists('users', 'id')->where('workspace_id', $this->user()->workspace_id),
            ],
        ];
    }
}
