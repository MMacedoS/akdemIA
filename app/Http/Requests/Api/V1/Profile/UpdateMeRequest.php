<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0.5', 'max:3'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:20', 'max:500'],
            'goal' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
