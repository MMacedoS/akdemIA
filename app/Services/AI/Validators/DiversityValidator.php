<?php

namespace App\Services\AI\Validators;

use Illuminate\Validation\ValidationException;

class DiversityValidator
{
    public function validate(array $data, array $context = []): void
    {
        $planningPayload = $context['planning_payload'] ?? [];
        $overusedMovements = array_fill_keys($planningPayload['training_memory']['overused_movements'] ?? [], true);
        $seenSpecificExercises = [];

        foreach (($data['weekly_plan'] ?? []) as $dayPlan) {
            foreach (($dayPlan['exercises'] ?? []) as $exercise) {
                if (($exercise['category'] ?? 'specific') !== 'specific') {
                    continue;
                }

                $remoteExerciseId = (string) ($exercise['remote_exercise_id'] ?? '');

                if ($remoteExerciseId === '') {
                    continue;
                }

                if (isset($seenSpecificExercises[$remoteExerciseId])) {
                    throw ValidationException::withMessages([
                        'workout' => 'Specific exercises cannot repeat within the same weekly plan: ' . $remoteExerciseId,
                    ]);
                }

                $seenSpecificExercises[$remoteExerciseId] = true;
            }
        }

        if ($overusedMovements === []) {
            return;
        }

        $weeklyOverusedCount = 0;

        foreach (array_keys($seenSpecificExercises) as $remoteExerciseId) {
            if (isset($overusedMovements[$remoteExerciseId])) {
                $weeklyOverusedCount++;
            }
        }

        if ($weeklyOverusedCount >= 2) {
            throw ValidationException::withMessages([
                'workout' => 'Weekly plan repeats overused movements beyond novelty threshold.',
            ]);
        }
    }
}
