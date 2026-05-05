<?php

namespace App\Services\MedicalData;

use App\Models\MedicalData\MedicalData;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MedicalDataService
{
    public function getByUser(User $user): ?MedicalData
    {
        return $user->medicalData()->first();
    }

    public function createByUser(User $user, array $data): MedicalData
    {
        if ($user->medicalData()->exists()) {
            throw ValidationException::withMessages([
                'medical_data' => 'Medical data already exists for this user.',
            ]);
        }

        $data['user_id'] = $user->id;

        return MedicalData::query()->create($data);
    }

    public function updateByUser(User $user, array $data): ?MedicalData
    {
        $medicalData = $user->medicalData()->first();

        if ($medicalData === null) {
            return null;
        }

        $medicalData->fill($data);
        $medicalData->save();

        return $medicalData;
    }
}
