<?php

namespace App\Http\Requests\Api\V1\MedicalData;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicalDataRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'injuries' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'diseases' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'medications' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'restrictions' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
