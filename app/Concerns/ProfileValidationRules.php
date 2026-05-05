<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\FormPatterns;
use Illuminate\Contracts\Validation\ValidationRule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => FormPatterns::name(),
            'email' => $this->emailRules($userId),
            'birth_date' => FormPatterns::date(),
            'gender' => ['nullable', 'string', 'max:30'],
            'height' => FormPatterns::decimal(false, 0, 999.99),
            'weight' => FormPatterns::decimal(false, 0, 999.99),
            'goal' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return FormPatterns::name();
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return FormPatterns::email($userId, (new User())->getTable());
    }
}
