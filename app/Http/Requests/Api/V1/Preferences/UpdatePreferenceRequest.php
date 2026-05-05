<?php

namespace App\Http\Requests\Api\V1\Preferences;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferenceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'preferred_foods' => ['sometimes', 'nullable', 'array'],
            'preferred_foods.*' => ['string', 'max:255'],
            'disliked_foods' => ['sometimes', 'nullable', 'array'],
            'disliked_foods.*' => ['string', 'max:255'],
            'drinks' => ['sometimes', 'nullable', 'array'],
            'drinks.*' => ['string', 'max:255'],
            'available_hours' => ['sometimes', 'nullable', 'array'],
            'available_hours.*' => ['string', 'max:255'],
            'training_frequency' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:14'],
        ];
    }
}
