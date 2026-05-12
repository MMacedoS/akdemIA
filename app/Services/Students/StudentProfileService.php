<?php

namespace App\Services\Students;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Services\MedicalData\MedicalDataService;
use App\Services\PhysicalData\PhysicalDataService;
use App\Services\Preferences\PreferenceService;
use App\Transformers\Profile\MeTransformer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class StudentProfileService
{
    public function __construct(
        private readonly MeTransformer $meTransformer,
        private readonly PhysicalDataService $physicalDataService,
        private readonly MedicalDataService $medicalDataService,
        private readonly PreferenceService $preferenceService,
    ) {}

    public function profileColumnsReady(): bool
    {
        return Schema::hasColumns('users', [
            'birth_date',
            'gender',
            'height',
            'weight',
            'goal',
            'phone',
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

    public function updateProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $preferencesData = is_array($data['preferences'] ?? null) ? $data['preferences'] : null;
            $userData = Arr::only($data, [
                'name',
                'email',
                'phone',
                'birth_date',
                'gender',
                'height',
                'weight',
                'goal',
            ]);

            if (! array_key_exists('goal', $userData)) {
                $goalFromPreferences = $this->resolveGoalFromPreferences($preferencesData);

                if ($goalFromPreferences !== null) {
                    $userData['goal'] = $goalFromPreferences;
                }
            }

            if ($userData !== []) {
                $user->fill($userData);
                $user->save();
            }

            $physicalData = $this->normalizePhysicalData($data['physical_data'] ?? null);

            if ($physicalData !== null) {
                $this->physicalDataService->upsertByUser($user, $physicalData);
            }

            $medicalData = $this->normalizeMedicalData($data['medical_data'] ?? null);

            if ($medicalData !== null) {
                $this->medicalDataService->upsertByUser($user, $medicalData);
            }

            $normalizedPreferences = $this->normalizePreferenceData($preferencesData);

            if ($normalizedPreferences !== null) {
                $this->preferenceService->upsertByUser($user, $normalizedPreferences);
            }

            return $user->fresh(['physicalData', 'medicalData', 'preference']) ?? $user;
        });
    }

    private function normalizePhysicalData(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        return Arr::except($data, ['imc']);
    }

    private function normalizeMedicalData(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        return Arr::only($data, ['injuries', 'diseases', 'medications', 'restrictions']);
    }

    private function normalizePreferenceData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $normalized = Arr::only($data, [
            'preferred_foods',
            'disliked_foods',
            'drinks',
            'available_hours',
            'training_frequency',
            'notifications_enabled',
        ]);

        if (! array_key_exists('training_frequency', $normalized) && array_key_exists('workout_days', $data)) {
            $normalized['training_frequency'] = $data['workout_days'];
        }

        return $normalized;
    }

    private function resolveGoalFromPreferences(?array $preferencesData): ?string
    {
        if (! is_array($preferencesData)) {
            return null;
        }

        foreach (['focus_areas', 'summary'] as $key) {
            if (! array_key_exists($key, $preferencesData)) {
                continue;
            }

            $value = trim((string) ($preferencesData[$key] ?? ''));

            return $value !== '' ? $value : null;
        }

        return null;
    }
}
