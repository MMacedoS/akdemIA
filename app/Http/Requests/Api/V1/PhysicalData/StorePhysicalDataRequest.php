<?php

namespace App\Http\Requests\Api\V1\PhysicalData;

use Illuminate\Foundation\Http\FormRequest;

class StorePhysicalDataRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body_fat_percentage' => ['nullable', 'numeric', 'min:2', 'max:75'],
            'activity_level' => ['required', 'string', 'in:sedentary,light,moderate,active,very_active'],
        ];
    }
}
