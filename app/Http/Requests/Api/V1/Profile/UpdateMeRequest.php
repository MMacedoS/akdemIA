<?php

namespace App\Http\Requests\Api\V1\Profile;

use App\Support\FormPatterns;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [...['sometimes'], ...FormPatterns::email($this->user()?->id, required: false)],
            'phone' => [...['sometimes'], ...FormPatterns::phone()],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0.5', 'max:3'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:20', 'max:500'],
            'goal' => ['sometimes', 'nullable', 'string', 'max:500'],
            'physical_data' => ['sometimes', 'array'],
            'physical_data.body_fat_percentage' => ['sometimes', 'nullable', 'numeric', 'min:2', 'max:75'],
            'physical_data.activity_level' => ['sometimes', 'nullable', 'string', 'max:50'],
            'physical_data.imc' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'medical_data' => ['sometimes', 'array'],
            'medical_data.injuries' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'medical_data.diseases' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'medical_data.medications' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'medical_data.restrictions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'preferences' => ['sometimes', 'array'],
            'preferences.preferred_foods' => ['sometimes', 'nullable', 'array'],
            'preferences.preferred_foods.*' => ['string', 'max:255'],
            'preferences.disliked_foods' => ['sometimes', 'nullable', 'array'],
            'preferences.disliked_foods.*' => ['string', 'max:255'],
            'preferences.drinks' => ['sometimes', 'nullable', 'array'],
            'preferences.drinks.*' => ['string', 'max:255'],
            'preferences.available_hours' => ['sometimes', 'nullable', 'array'],
            'preferences.available_hours.*' => ['string', 'max:255'],
            'preferences.training_frequency' => ['sometimes', 'nullable', 'string', 'max:255'],
            'preferences.workout_days' => ['sometimes', 'nullable', 'string', 'max:255'],
            'preferences.focus_areas' => ['sometimes', 'nullable', 'string', 'max:500'],
            'preferences.notifications_enabled' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->exists('email')) {
            $normalized['email'] = FormPatterns::normalizeEmail($this->input('email'));
        }

        if ($this->exists('phone')) {
            $normalized['phone'] = FormPatterns::formatPhone($this->input('phone'));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
