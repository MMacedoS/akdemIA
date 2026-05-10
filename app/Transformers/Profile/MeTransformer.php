<?php

namespace App\Transformers\Profile;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MeTransformer
{
    public function __construct(
        private readonly StudentTrainerTransformer $studentTrainerTransformer,
    ) {}

    public function transform(User $user, mixed $tenant, ?User $assignedTrainee = null): array
    {
        $user->loadMissing(['physicalData', 'medicalData', 'preference']);

        $payload = [
            'id' => $user->id,
            'tenant_id' => $tenant instanceof Tenant ? $tenant->id : null,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'birth_date' => $user->birth_date?->toDateString(),
            'gender' => $user->gender,
            'height' => $user->height,
            'weight' => $user->weight,
            'goal' => $user->goal,
            'physical_data' => $this->transformRelation($user->physicalData),
            'medical_data' => $this->transformRelation($user->medicalData),
            'preferences' => $this->transformRelation($user->preference),
        ];

        if ($user->profileType() !== Role::STUDENT) {
            return $payload;
        }

        $payload['assigned_trainer'] = $assignedTrainee === null
            ? null
            : $this->studentTrainerTransformer->transformAssigned($assignedTrainee);

        return $payload;
    }

    private function transformRelation(?Model $model): ?array
    {
        if ($model === null) {
            return null;
        }

        return $model->attributesToArray();
    }
}
