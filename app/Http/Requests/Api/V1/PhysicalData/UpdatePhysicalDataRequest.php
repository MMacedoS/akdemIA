<?php

namespace App\Http\Requests\Api\V1\PhysicalData;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePhysicalDataRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body_fat_percentage' => ['sometimes', 'nullable', 'numeric', 'min:2', 'max:75'],
            'activity_level' => ['sometimes', 'string', 'in:sedentary,light,moderate,active,very_active'],
        ];
    }
}
