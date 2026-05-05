<?php

namespace App\Http\Requests\Api\V1\MedicalData;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicalDataRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'injuries' => ['nullable', 'string', 'max:5000'],
            'diseases' => ['nullable', 'string', 'max:5000'],
            'medications' => ['nullable', 'string', 'max:5000'],
            'restrictions' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
