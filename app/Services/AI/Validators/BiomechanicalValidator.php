<?php

namespace App\Services\AI\Validators;

use App\Models\Workout\ExerciseMediaCache;
use Illuminate\Validation\ValidationException;

class BiomechanicalValidator
{
    public function validate(array $data, array $context = []): void
    {
        $planningPayload = $context['planning_payload'] ?? [];
        $plannedDays = collect($planningPayload['selected_days'] ?? [])->keyBy('label');
        $previousDayKeys = null;

        foreach (($data['weekly_plan'] ?? []) as $index => $dayPlan) {
            $resolvedFocusTokens = [];
            $currentDayKeys = [];
            $dayLabel = (string) ($dayPlan['day'] ?? '');
            $plannedDay = $plannedDays->get($dayLabel) ?? $plannedDays->values()->get($index);
            $allowedFocusTokens = is_array($plannedDay['allowed_focus_tokens'] ?? null)
                ? $plannedDay['allowed_focus_tokens']
                : [];
            $allowedExerciseIds = collect($plannedDay['selected_exercises'] ?? [])
                ->map(fn(array $exercise): string => (string) ($exercise['remote_exercise_id'] ?? ''))
                ->filter()
                ->values()
                ->all();

            foreach (($dayPlan['exercises'] ?? []) as $exercise) {
                $remoteExerciseId = (string) ($exercise['remote_exercise_id'] ?? '');
                $currentDayKeys[] = $remoteExerciseId;

                if (($exercise['category'] ?? 'specific') !== 'specific') {
                    continue;
                }

                $catalogExercise = ExerciseMediaCache::query()
                    ->where('remote_exercise_id', $remoteExerciseId)
                    ->first();

                $focusToken = $this->normalizeFocusToken(
                    (string) data_get($catalogExercise?->payload ?? [], 'bodyPart', data_get($catalogExercise?->payload ?? [], 'target', ''))
                );

                if ($focusToken !== null) {
                    $resolvedFocusTokens[] = $focusToken;
                }

                if ($allowedExerciseIds !== [] && ! in_array($remoteExerciseId, $allowedExerciseIds, true)) {
                    throw ValidationException::withMessages([
                        'workout' => 'Exercise is outside deterministic exercise selection for day: ' . ($dayPlan['focus'] ?? 'Treino'),
                    ]);
                }
            }

            $this->assertFocusCoherence(
                (string) ($dayPlan['focus'] ?? 'Treino geral'),
                $this->normalizeFocusToken((string) ($dayPlan['focus'] ?? '')),
                $resolvedFocusTokens,
                $allowedFocusTokens,
            );

            if (is_array($previousDayKeys)) {
                $repeated = array_values(array_intersect($previousDayKeys, $currentDayKeys));

                if ($repeated !== []) {
                    throw ValidationException::withMessages([
                        'workout' => 'Consecutive days cannot repeat the same exercise ids: ' . implode(', ', $repeated),
                    ]);
                }
            }

            $previousDayKeys = $currentDayKeys;
        }
    }

    private function assertFocusCoherence(string $focus, ?string $dayFocusToken, array $resolvedFocusTokens, array $allowedFocusTokens): void
    {
        $resolvedFocusTokens = array_values(array_filter(array_unique($resolvedFocusTokens)));

        if ($resolvedFocusTokens === []) {
            return;
        }

        if ($allowedFocusTokens !== []) {
            foreach ($resolvedFocusTokens as $resolvedFocusToken) {
                if (! in_array($resolvedFocusToken, $allowedFocusTokens, true)) {
                    throw ValidationException::withMessages([
                        'workout' => 'Day focus does not match deterministic biomechanical plan for day: ' . $focus,
                    ]);
                }
            }

            return;
        }

        if (count($resolvedFocusTokens) > 1) {
            throw ValidationException::withMessages([
                'workout' => 'Specific exercises do not share a coherent biomechanical focus for day: ' . $focus,
            ]);
        }

        if ($dayFocusToken !== null && $resolvedFocusTokens[0] !== $dayFocusToken) {
            throw ValidationException::withMessages([
                'workout' => 'Day focus does not match retrieved exercise biomechanics for day: ' . $focus,
            ]);
        }
    }

    private function normalizeFocusToken(string $value): ?string
    {
        $normalized = mb_strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        return match (true) {
            str_contains($normalized, 'cardio') => 'cardio',
            str_contains($normalized, 'chest'), str_contains($normalized, 'peito') => 'peito',
            str_contains($normalized, 'back'), str_contains($normalized, 'costa') => 'costas',
            str_contains($normalized, 'shoulder'), str_contains($normalized, 'ombro') => 'ombro',
            str_contains($normalized, 'upper legs'), str_contains($normalized, 'lower legs'), str_contains($normalized, 'quadr'), str_contains($normalized, 'hamstring'), str_contains($normalized, 'glute'), str_contains($normalized, 'perna'), str_contains($normalized, 'leg') => 'pernas',
            str_contains($normalized, 'waist'), str_contains($normalized, 'abd') || str_contains($normalized, 'core') => 'core',
            str_contains($normalized, 'biceps'), str_contains($normalized, 'triceps'), str_contains($normalized, 'forearms'), str_contains($normalized, 'arm'), str_contains($normalized, 'bra') => 'bracos',
            default => null,
        };
    }
}
