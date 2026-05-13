<?php

namespace App\Services\AI\Validators;

use App\Models\Workout\ExerciseMediaCache;
use Illuminate\Validation\ValidationException;

class StructuralValidator
{
    public function validate(array $data, array $context = []): array
    {
        if (! isset($data['weekly_plan']) || ! is_array($data['weekly_plan']) || $data['weekly_plan'] === []) {
            throw ValidationException::withMessages([
                'workout' => 'Missing weekly_plan array.',
            ]);
        }

        $expectedTrainingDays = $context['expected_training_days'] ?? null;

        if ($expectedTrainingDays !== null && count($data['weekly_plan']) !== $expectedTrainingDays) {
            throw ValidationException::withMessages([
                'workout' => 'weekly_plan must contain exactly ' . $expectedTrainingDays . ' day(s) according to training_frequency.',
            ]);
        }

        $normalizedPlan = [];

        foreach ($data['weekly_plan'] as $dayPlan) {
            if (! is_array($dayPlan)) {
                continue;
            }

            $rawExercises = $dayPlan['exercises'] ?? [];

            if (! is_array($rawExercises) || $rawExercises === []) {
                throw ValidationException::withMessages([
                    'workout' => 'Each day must contain at least one exercise.',
                ]);
            }

            if (count($rawExercises) !== 5) {
                throw ValidationException::withMessages([
                    'workout' => 'Each day must contain exactly 5 exercises.',
                ]);
            }

            $exercises = [];
            $specificCount = 0;
            $cardioCount = 0;
            $dayExerciseKeys = [];

            foreach ($rawExercises as $exercise) {
                if (! is_array($exercise)) {
                    continue;
                }

                $name = trim((string) ($exercise['name'] ?? ''));

                if ($name === '') {
                    throw ValidationException::withMessages([
                        'workout' => 'Exercise name is required.',
                    ]);
                }

                $category = mb_strtolower(trim((string) ($exercise['category'] ?? '')));

                if (! in_array($category, ['specific', 'cardio'], true)) {
                    throw ValidationException::withMessages([
                        'workout' => 'Each exercise must contain category as specific or cardio.',
                    ]);
                }

                if ($category === 'specific') {
                    $specificCount++;
                } else {
                    $cardioCount++;
                }

                $sets = (int) ($exercise['sets'] ?? 0);

                if ($sets <= 0 || $sets > 6) {
                    throw ValidationException::withMessages([
                        'workout' => 'Invalid sets value for exercise: ' . $name,
                    ]);
                }

                $reps = trim((string) ($exercise['reps'] ?? ''));

                if (! $this->isValidReps($reps)) {
                    throw ValidationException::withMessages([
                        'workout' => 'Invalid reps format for exercise: ' . $name,
                    ]);
                }

                $rest = trim((string) ($exercise['rest'] ?? ''));

                if ($rest === '') {
                    throw ValidationException::withMessages([
                        'workout' => 'Rest is required for exercise: ' . $name,
                    ]);
                }

                $notes = (string) ($exercise['notes'] ?? 'Execute com controle.');
                $steps = $this->normalizeSteps($exercise['steps'] ?? null, $notes);
                $remoteExerciseId = trim((string) ($exercise['remote_exercise_id'] ?? ''));

                if ($remoteExerciseId === '') {
                    throw ValidationException::withMessages([
                        'workout' => 'Each exercise must contain remote_exercise_id.',
                    ]);
                }

                $catalogExercise = $this->resolveCatalogExercise(
                    $remoteExerciseId,
                    $exercise['workoutx_name'] ?? data_get($exercise, 'workoutx_lookup.name'),
                    $name,
                );

                $remoteExerciseId = trim((string) ($catalogExercise?->remote_exercise_id ?? $remoteExerciseId));
                $workoutxName = $this->normalizeWorkoutxName(
                    $catalogExercise?->workoutx_name
                        ?? ($exercise['workoutx_name'] ?? data_get($exercise, 'workoutx_lookup.name')),
                    $name,
                );
                $exerciseKey = $remoteExerciseId !== '' ? 'id:' . $remoteExerciseId : 'slug:' . $workoutxName;

                if (isset($dayExerciseKeys[$exerciseKey])) {
                    throw ValidationException::withMessages([
                        'workout' => 'Duplicate exercise found in the same day: ' . $name,
                    ]);
                }

                $dayExerciseKeys[$exerciseKey] = true;

                if (count($steps) < 2 || count($steps) > 5) {
                    throw ValidationException::withMessages([
                        'workout' => 'Each exercise must contain between 2 and 5 steps: ' . $name,
                    ]);
                }

                $exercises[] = [
                    'name' => $name,
                    'category' => $category,
                    'sets' => $sets,
                    'reps' => $reps,
                    'rest' => $rest,
                    'notes' => $notes,
                    'steps' => $steps,
                    'remote_exercise_id' => $remoteExerciseId,
                    'workoutx_name' => $workoutxName,
                    'exercise_media_path' => trim((string) ($exercise['exercise_media_path'] ?? '')),
                    'exercise_media_url' => trim((string) ($exercise['exercise_media_url'] ?? '')),
                ];
            }

            if ($specificCount !== 4 || $cardioCount !== 1) {
                throw ValidationException::withMessages([
                    'workout' => 'Each day must contain exactly 4 specific exercises and 1 cardio exercise.',
                ]);
            }

            $normalizedPlan[] = [
                'day' => (string) ($dayPlan['day'] ?? 'Dia'),
                'focus' => (string) ($dayPlan['focus'] ?? 'Treino geral'),
                'exercises' => $exercises,
            ];
        }

        if ($normalizedPlan === []) {
            throw ValidationException::withMessages([
                'workout' => 'weekly_plan contains no valid day.',
            ]);
        }

        return ['weekly_plan' => $normalizedPlan];
    }

    private function normalizeSteps(mixed $steps, string $notes): array
    {
        if (is_array($steps)) {
            $normalizedSteps = collect($steps)
                ->map(fn(mixed $step): string => trim((string) $step))
                ->filter()
                ->values()
                ->take(5)
                ->all();

            if ($normalizedSteps !== []) {
                return $normalizedSteps;
            }
        }

        $fallbackSteps = collect(preg_split('/[\.;\n]+/', $notes) ?: [])
            ->map(fn(mixed $step): string => trim((string) $step))
            ->filter()
            ->values()
            ->take(4)
            ->all();

        if (count($fallbackSteps) >= 2) {
            return $fallbackSteps;
        }

        return [
            'Prepare a postura inicial com estabilidade.',
            'Execute o movimento de forma controlada.',
            'Retorne a posicao inicial sem perder a tecnica.',
        ];
    }

    private function resolveCatalogExercise(string $remoteExerciseId, mixed $workoutxName, string $name): ?ExerciseMediaCache
    {
        if ($remoteExerciseId !== '') {
            $catalogExercise = ExerciseMediaCache::query()
                ->where('remote_exercise_id', $remoteExerciseId)
                ->first();

            if ($catalogExercise instanceof ExerciseMediaCache) {
                return $catalogExercise;
            }

            $catalogExercise = ExerciseMediaCache::query()
                ->where('workoutx_name', $this->normalizeWorkoutxName($remoteExerciseId, $name))
                ->first();

            if ($catalogExercise instanceof ExerciseMediaCache) {
                return $catalogExercise;
            }
        }

        $normalizedWorkoutxName = $this->normalizeWorkoutxName($workoutxName, $name);

        if ($normalizedWorkoutxName === '') {
            return null;
        }

        return ExerciseMediaCache::query()
            ->where('workoutx_name', $normalizedWorkoutxName)
            ->first();
    }

    private function normalizeWorkoutxName(mixed $value, string $name): string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            $normalized = $name;
        }

        $normalized = mb_strtolower($normalized);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);

        if (is_string($transliterated) && trim($transliterated) !== '') {
            $normalized = $transliterated;
        }

        $normalized = preg_replace('/[^a-z0-9\s-]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[-\s]+/', '-', trim($normalized)) ?? trim($normalized);
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'body-weight-exercise';
    }

    private function isValidReps(string $reps): bool
    {
        $reps = trim($reps);

        if ($reps === '') {
            return false;
        }

        if (preg_match('/^\d{1,2}$/', $reps) === 1) {
            return true;
        }

        if (preg_match('/^\d{1,3}\s*(s|sec|secs|seg|segs|segundo|segundos|min|mins|minuto|minutos)$/i', $reps) === 1) {
            return true;
        }

        if (preg_match('/^(\d{1,3})\s*-\s*(\d{1,3})\s*(s|sec|secs|seg|segs|segundo|segundos|min|mins|minuto|minutos)$/i', $reps, $matches) === 1) {
            return (int) $matches[1] > 0 && (int) $matches[2] >= (int) $matches[1];
        }

        if (preg_match('/^(\d{1,3})\s*(a|ate|até)\s*(\d{1,3})\s*(s|sec|secs|seg|segs|segundo|segundos|min|mins|minuto|minutos)$/i', $reps, $matches) === 1) {
            return (int) $matches[1] > 0 && (int) $matches[3] >= (int) $matches[1];
        }

        if (preg_match('/^(\d{1,3})\s*(s|sec|secs|seg|segs|segundo|segundos|min|mins|minuto|minutos|h|hr|hora|horas)\b.*$/i', $reps, $matches) === 1) {
            return (int) $matches[1] > 0;
        }

        return preg_match('/^(\d{1,3})\s*(-|a|ate|até)\s*(\d{1,3})\s*(s|sec|secs|seg|segs|segundo|segundos|min|mins|minuto|minutos|h|hr|hora|horas)\b.*$/i', $reps, $matches) === 1
            && (int) $matches[1] > 0
            && (int) $matches[3] >= (int) $matches[1];
    }
}
