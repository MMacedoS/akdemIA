<?php

namespace App\Services\Workout\Planning;

use App\DTOs\AI\WorkoutGenerationContext;
use App\DTOs\AI\WorkoutRetrievalResult;
use App\Exceptions\AI\WorkoutValidationException;
use App\Models\Workout\ExerciseRelationship;

class WorkoutRepairEngine
{
    public function repair(
        WorkoutGenerationContext $context,
        WorkoutRetrievalResult $retrieval,
        array $planningPayload,
        array $candidatePlan,
        WorkoutValidationException $exception,
    ): array {
        $normalized = $candidatePlan;
        $plannedDays = collect($planningPayload['selected_days'] ?? [])->keyBy('label');

        if (! is_array($normalized['weekly_plan'] ?? null)) {
            return $this->rebuildFromPlanning($planningPayload);
        }

        foreach ($normalized['weekly_plan'] as $index => $dayPlan) {
            $label = (string) ($dayPlan['day'] ?? '');
            $plannedDay = $plannedDays->get($label) ?? $plannedDays->values()->get($index);

            if (! is_array($plannedDay)) {
                continue;
            }

            $normalized['weekly_plan'][$index]['focus'] = (string) ($plannedDay['focus'] ?? $plannedDay['focus_label'] ?? $dayPlan['focus'] ?? 'Treino');
            $normalized['weekly_plan'][$index]['exercises'] = $this->repairDayExercises(
                is_array($dayPlan['exercises'] ?? null) ? $dayPlan['exercises'] : [],
                $plannedDay['selected_exercises'] ?? [],
                $exception,
            );
        }

        return $normalized;
    }

    private function rebuildFromPlanning(array $planningPayload): array
    {
        return [
            'weekly_plan' => array_map(function (array $day): array {
                return [
                    'day' => (string) ($day['label'] ?? $day['day'] ?? 'Dia'),
                    'focus' => (string) ($day['focus'] ?? $day['focus_label'] ?? 'Treino'),
                    'exercises' => array_map(fn(array $exercise): array => $this->materializeExercise($exercise), $day['selected_exercises'] ?? []),
                ];
            }, $planningPayload['selected_days'] ?? []),
        ];
    }

    private function repairDayExercises(array $currentExercises, array $plannedExercises, WorkoutValidationException $exception): array
    {
        $message = mb_strtolower($exception->getMessage());
        $usedIds = [];
        $resolved = [];

        foreach ($plannedExercises as $plannedExercise) {
            $plannedId = (string) ($plannedExercise['remote_exercise_id'] ?? '');
            $current = $this->findExerciseById($currentExercises, $plannedId);
            $exercise = is_array($current) ? array_merge($plannedExercise, $current) : $plannedExercise;
            $exercise['remote_exercise_id'] = $plannedId;
            $exercise['workoutx_name'] = (string) ($plannedExercise['workoutx_name'] ?? $exercise['workoutx_name'] ?? '');
            $exercise['category'] = (string) ($plannedExercise['category'] ?? $exercise['category'] ?? 'specific');

            if ($exercise['category'] === 'specific' && (str_contains($message, 'duplicate') || str_contains($message, 'repeat'))) {
                if (isset($usedIds[$plannedId])) {
                    $replacement = $this->resolveRelationshipAlternative($plannedExercise, $usedIds) ?? $plannedExercise;
                    $exercise = array_merge($exercise, $replacement);
                }
            }

            $resolvedExercise = $this->materializeExercise($exercise);
            $usedIds[(string) ($resolvedExercise['remote_exercise_id'] ?? '')] = true;
            $resolved[] = $resolvedExercise;
        }

        return array_slice($resolved, 0, 5);
    }

    private function resolveRelationshipAlternative(array $plannedExercise, array $usedIds): ?array
    {
        $sourceId = (string) ($plannedExercise['remote_exercise_id'] ?? '');

        if ($sourceId === '') {
            return null;
        }

        $alternative = ExerciseRelationship::query()
            ->where('source_exercise_id', $sourceId)
            ->whereIn('relationship_type', ['substitute_for', 'variation_of', 'equipment_alternative', 'injury_safe_alternative'])
            ->orderByDesc('score')
            ->first();

        if ($alternative === null || isset($usedIds[$alternative->target_exercise_id])) {
            return null;
        }

        return array_merge($plannedExercise, [
            'remote_exercise_id' => (string) $alternative->target_exercise_id,
            'exercise_id' => (string) $alternative->target_exercise_id,
            'reason' => 'relationship_' . $alternative->relationship_type,
        ]);
    }

    private function findExerciseById(array $exercises, string $remoteExerciseId): ?array
    {
        foreach ($exercises as $exercise) {
            if ((string) ($exercise['remote_exercise_id'] ?? '') === $remoteExerciseId) {
                return is_array($exercise) ? $exercise : null;
            }
        }

        return null;
    }

    private function materializeExercise(array $exercise): array
    {
        return [
            'name' => (string) ($exercise['name'] ?? 'Exercicio'),
            'category' => (string) ($exercise['category'] ?? 'specific'),
            'sets' => max(1, min(6, (int) ($exercise['sets'] ?? 3))),
            'reps' => trim((string) ($exercise['reps'] ?? '10-12')) ?: '10-12',
            'rest' => trim((string) ($exercise['rest'] ?? ($exercise['category'] ?? 'specific') === 'cardio' ? '0s' : '60s')) ?: (($exercise['category'] ?? 'specific') === 'cardio' ? '0s' : '60s'),
            'notes' => trim((string) ($exercise['notes'] ?? 'Execute de forma controlada e sem perder a tecnica.')) ?: 'Execute de forma controlada e sem perder a tecnica.',
            'steps' => $this->normalizeSteps($exercise['steps'] ?? null),
            'remote_exercise_id' => trim((string) ($exercise['remote_exercise_id'] ?? $exercise['exercise_id'] ?? '')),
            'workoutx_name' => trim((string) ($exercise['workoutx_name'] ?? '')),
        ];
    }

    private function normalizeSteps(mixed $steps): array
    {
        if (is_array($steps)) {
            $normalized = array_values(array_filter(array_map(static fn(mixed $step): string => trim((string) $step), $steps)));

            if (count($normalized) >= 2) {
                return array_slice($normalized, 0, 5);
            }
        }

        return [
            'Prepare a postura inicial com estabilidade.',
            'Execute o movimento de forma controlada.',
            'Retorne a posicao inicial sem perder a tecnica.',
        ];
    }
}
