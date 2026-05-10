<?php

namespace App\Services\Students;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Transformers\Profile\MeTransformer;
use Illuminate\Support\Facades\Schema;

class StudentProfileService
{
    public function __construct(
        private readonly MeTransformer $meTransformer,
    ) {}

    public function profileColumnsReady(): bool
    {
        return Schema::hasColumns('users', [
            'birth_date',
            'gender',
            'height',
            'weight',
            'goal',
        ]);
    }

    public function allowsSelfService(User $user, mixed $tenant): bool
    {
        if ($tenant instanceof Tenant) {
            return $user->belongsToTenant($tenant);
        }

        return $user->profileType() === Role::STUDENT;
    }

    public function profilePayload(User $user, mixed $tenant): array
    {
        $assignedTrainee = $user->assignedTrainee($tenant instanceof Tenant ? $tenant : null);

        return $this->meTransformer->transform($user, $tenant, $assignedTrainee);
    }
}
