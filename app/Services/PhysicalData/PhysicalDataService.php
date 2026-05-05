<?php

namespace App\Services\PhysicalData;

use App\Models\PhysicalData\PhysicalData;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PhysicalDataService
{
    public function getByUser(User $user): ?PhysicalData
    {
        return $user->physicalData()->first();
    }

    public function createByUser(User $user, array $data): PhysicalData
    {
        if ($user->physicalData()->exists()) {
            throw ValidationException::withMessages([
                'physical_data' => 'Physical data already exists for this user.',
            ]);
        }

        $data['user_id'] = $user->id;
        $data['imc'] = $this->calculateImc($user);

        return PhysicalData::query()->create($data);
    }

    public function updateByUser(User $user, array $data): ?PhysicalData
    {
        $physicalData = $user->physicalData()->first();

        if ($physicalData === null) {
            return null;
        }

        $data['imc'] = $this->calculateImc($user);
        $physicalData->fill($data);
        $physicalData->save();

        return $physicalData;
    }

    private function calculateImc(User $user): float
    {
        $height = is_numeric($user->height) ? (float) $user->height : 0.0;
        $weight = is_numeric($user->weight) ? (float) $user->weight : 0.0;

        if ($height <= 0 || $weight <= 0) {
            throw ValidationException::withMessages([
                'imc' => 'Height and weight must be informed on profile to calculate IMC.',
            ]);
        }

        return round($weight / ($height * $height), 2);
    }
}
