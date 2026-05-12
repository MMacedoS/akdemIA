<?php

namespace App\Services\AI;

use App\DTOs\AI\WorkoutGenerationContext;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use RuntimeException;

class WorkoutGenerationContextFactory
{
    public function make(
        User $user,
        ?Tenant $tenant,
        bool $conservativeMode = false,
        ?string $adjustmentRequest = null,
    ): WorkoutGenerationContext {
        $physicalData = $user->physicalData()->first();
        $medicalData = $user->medicalData()->first();
        $preference = $user->preference()->first();

        if ($physicalData === null || $medicalData === null) {
            throw new RuntimeException('Missing physical or medical data for workout generation.');
        }

        $previousWorkout = Workout::query()
            ->where('user_id', $user->id)
            ->where('status', 'done')
            ->latest('id')
            ->first();

        return new WorkoutGenerationContext(
            userId: $user->id,
            tenantId: $tenant?->id,
            profile: [
                'age' => $user->birth_date?->age,
                'gender' => $user->gender,
                'height' => $user->height,
                'weight' => $user->weight,
                'imc' => is_numeric($physicalData->imc) ? (float) $physicalData->imc : null,
                'activity_level' => $physicalData->activity_level,
                'training_frequency' => $preference?->training_frequency,
                'goal' => $user->goal,
                'restrictions' => $medicalData->restrictions,
                'injuries' => $medicalData->injuries,
            ],
            previousWorkoutPlan: is_array($previousWorkout?->workout_plan) ? $previousWorkout->workout_plan : [],
            conservativeMode: $conservativeMode,
            adjustmentRequest: trim((string) $adjustmentRequest) !== '' ? trim((string) $adjustmentRequest) : null,
            expectedTrainingDays: $this->resolveTrainingDays((string) ($preference?->training_frequency ?? '')),
        );
    }

    private function resolveTrainingDays(string $trainingFrequency): ?int
    {
        if ($trainingFrequency === '') {
            return null;
        }

        if (preg_match('/(\d{1,2})/', $trainingFrequency, $matches) !== 1) {
            return null;
        }

        $days = (int) $matches[1];

        return $days > 0 ? $days : null;
    }
}
