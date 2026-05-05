<?php

namespace App\Http\Requests\Api\V1\Preferences;

use Illuminate\Foundation\Http\FormRequest;

class StorePreferenceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'preferred_foods' => ['nullable', 'array'],
            'preferred_foods.*' => ['string', 'max:255'],
            'disliked_foods' => ['nullable', 'array'],
            'disliked_foods.*' => ['string', 'max:255'],
            'drinks' => ['nullable', 'array'],
            'drinks.*' => ['string', 'max:255'],
            'available_hours' => ['nullable', 'array'],
            'available_hours.*' => ['string', 'max:255'],
            'training_frequency' => ['nullable', 'integer', 'min:1', 'max:14'],
        ];
    }
}
