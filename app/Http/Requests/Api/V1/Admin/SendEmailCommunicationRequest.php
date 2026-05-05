<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendEmailCommunicationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'target_type' => ['required', Rule::in(['all', 'role', 'users'])],
            'role' => [
                Rule::requiredIf(fn(): bool => $this->input('target_type') === 'role'),
                Rule::in([Role::ADMIN->value, Role::TRAINER->value, Role::STUDENT->value]),
            ],
            'user_ids' => [
                Rule::requiredIf(fn(): bool => $this->input('target_type') === 'users'),
                'array',
                'min:1',
            ],
            'user_ids.*' => ['integer', 'distinct'],
        ];
    }
}
