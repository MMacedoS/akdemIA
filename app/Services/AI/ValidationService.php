<?php

namespace App\Services\AI;

use App\Models\Workout\ExerciseMediaCache;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ValidationService
{
    private array $safetyFlags = [];
    private ?int $expectedTrainingDays = null;

    public function __construct() {}

    public function validateUserForWorkout(User $user): array
    {
        $physicalData = $user->physicalData()->first();
        $medicalData = $user->medicalData()->first();
        $preference = $user->preference()->first();

        if ($physicalData === null || $medicalData === null) {
            throw ValidationException::withMessages([
                'workout' => 'Physical and medical data are required before workout generation.',
            ]);
        }

        $age = $this->resolveAge($user);
        $activityLevel = (string) $physicalData->activity_level;
        $imc = is_numeric($physicalData->imc) ? (float) $physicalData->imc : null;

        $injuriesText = (string) ($medicalData->injuries ?? '');
        $restrictionsText = (string) ($medicalData->restrictions ?? '');

        $flags = [
            'severe_injury' => $this->hasSevereInjury($injuriesText, $restrictionsText),
            'high_imc' => $imc !== null && $imc >= 35,
            'beginner' => in_array($activityLevel, ['sedentary', 'light'], true),
        ];

        $this->safetyFlags = $flags;
        $this->expectedTrainingDays = $this->resolveTrainingDays($preference?->training_frequency);

        return [
            'age' => $age,
            'gender' => $user->gender,
            'height' => $user->height,
            'weight' => $user->weight,
            'imc' => $imc,
            'activity_level' => $activityLevel,
            'goal' => $user->goal,
            'restrictions' => $restrictionsText,
            'injuries' => $injuriesText,
            'flags' => $flags,
        ];
    }

    public function validateWorkoutResponse(array $data): array
    {
        if (! isset($data['weekly_plan']) || ! is_array($data['weekly_plan']) || $data['weekly_plan'] === []) {
            throw ValidationException::withMessages([
                'workout' => 'Missing weekly_plan array.',
            ]);
        }

        $prohibitedPatterns = [
            '/levantamento\s*terra\s*pesado/i',
            '/agachamento\s*profundo\s*com\s*carga/i',
        ];

        if (($this->safetyFlags['severe_injury'] ?? false) === true) {
            $prohibitedPatterns[] = '/(burpee|box jump|saltos?|sprint|pliometri)/i';
        }

        if (($this->safetyFlags['high_imc'] ?? false) === true) {
            $prohibitedPatterns[] = '/(corrida intensa|tiro|sprint|hiit pesado)/i';
        }

        if (($this->safetyFlags['beginner'] ?? false) === true) {
            $prohibitedPatterns[] = '/(muscle up|snatch|clean and jerk|pistol squat)/i';
        }

        $normalizedPlan = [];

        if ($this->expectedTrainingDays !== null && count($data['weekly_plan']) !== $this->expectedTrainingDays) {
            throw ValidationException::withMessages([
                'workout' => 'weekly_plan must contain exactly ' . $this->expectedTrainingDays . ' day(s) according to training_frequency.',
            ]);
        }

        foreach ($data['weekly_plan'] as $dayPlan) {
            if (! is_array($dayPlan)) {
                continue;
            }

            $focus = (string) ($dayPlan['focus'] ?? 'Treino geral');
            $dayFocusToken = $this->normalizeFocusToken($focus);

            $exercises = [];
            $rawExercises = $dayPlan['exercises'] ?? [];
            $resolvedFocusTokens = [];
            $dayExerciseKeys = [];

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

            $specificCount = 0;
            $cardioCount = 0;

            foreach ($rawExercises as $exercise) {
                if (! is_array($exercise)) {
                    continue;
                }

                $name = (string) ($exercise['name'] ?? '');
                if ($name === '') {
                    throw ValidationException::withMessages([
                        'workout' => 'Exercise name is required.',
                    ]);
                }

                foreach ($prohibitedPatterns as $pattern) {
                    if (preg_match($pattern, $name) === 1) {
                        throw ValidationException::withMessages([
                            'workout' => 'Prohibited exercise found: ' . $name,
                        ]);
                    }
                }

                $category = mb_strtolower(trim((string) ($exercise['category'] ?? '')));
                if (! in_array($category, ['specific', 'cardio'], true)) {
                    throw ValidationException::withMessages([
                        'workout' => 'Each exercise must contain category as specific or cardio.',
                    ]);
                }

                if ($category === 'specific') {
                    $specificCount++;
                }

                if ($category === 'cardio') {
                    $cardioCount++;
                }

                $sets = (int) ($exercise['sets'] ?? 0);
                if ($sets <= 0 || $sets > 6) {
                    throw ValidationException::withMessages([
                        'workout' => 'Invalid sets value for exercise: ' . $name,
                    ]);
                }

                $reps = (string) ($exercise['reps'] ?? '');
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
                $steps = $this->normalizeSteps($exercise['steps'] ?? null, $name, $notes);
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

                $resolvedFocusToken = $category === 'cardio'
                    ? 'cardio'
                    : $this->normalizeFocusToken(
                        (string) data_get($catalogExercise?->payload ?? [], 'bodyPart', data_get($catalogExercise?->payload ?? [], 'target', ''))
                    );

                if ($category === 'specific') {
                    $resolvedFocusTokens[] = $resolvedFocusToken;
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

            if ($exercises === []) {
                throw ValidationException::withMessages([
                    'workout' => 'Each day must contain valid exercises.',
                ]);
            }

            $this->assertFocusCoherence($focus, $dayFocusToken, $resolvedFocusTokens);

            $normalizedPlan[] = [
                'day' => (string) ($dayPlan['day'] ?? 'Dia'),
                'focus' => $focus,
                'exercises' => $exercises,
            ];
        }

        if ($normalizedPlan === []) {
            throw ValidationException::withMessages([
                'workout' => 'weekly_plan contains no valid day.',
            ]);
        }

        $this->assertNoConsecutiveRepeats($normalizedPlan);

        return ['weekly_plan' => $normalizedPlan];
    }

    public function safetyFlags(): array
    {
        return $this->safetyFlags;
    }

    private function normalizeSteps(mixed $steps, string $name, string $notes): array
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

    private function assertFocusCoherence(string $focus, ?string $dayFocusToken, array $resolvedFocusTokens): void
    {
        $resolvedFocusTokens = array_values(array_filter($resolvedFocusTokens));

        if ($resolvedFocusTokens === []) {
            return;
        }

        $uniqueTokens = array_values(array_unique($resolvedFocusTokens));

        if (count($uniqueTokens) > 1) {
            throw ValidationException::withMessages([
                'workout' => 'Specific exercises do not share a coherent biomechanical focus for day: ' . $focus,
            ]);
        }

        if ($dayFocusToken !== null && $uniqueTokens[0] !== $dayFocusToken) {
            throw ValidationException::withMessages([
                'workout' => 'Day focus does not match retrieved exercise biomechanics for day: ' . $focus,
            ]);
        }
    }

    private function assertNoConsecutiveRepeats(array $normalizedPlan): void
    {
        $previousDayKeys = null;

        foreach ($normalizedPlan as $dayPlan) {
            $currentDayKeys = collect($dayPlan['exercises'] ?? [])
                ->map(fn(array $exercise): string => (string) ($exercise['remote_exercise_id'] ?? $exercise['workoutx_name'] ?? ''))
                ->filter()
                ->values()
                ->all();

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

    private function normalizeFocusToken(mixed $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        return match (true) {
            str_contains($normalized, 'cardio') => 'cardio',
            str_contains($normalized, 'chest'), str_contains($normalized, 'peito') => 'peito',
            str_contains($normalized, 'back'), str_contains($normalized, 'costa') => 'costas',
            str_contains($normalized, 'shoulder'), str_contains($normalized, 'ombro') => 'ombro',
            str_contains($normalized, 'upper legs'), str_contains($normalized, 'lower legs'), str_contains($normalized, 'quadr'), str_contains($normalized, 'hamstring'), str_contains($normalized, 'glute'), str_contains($normalized, 'perna') => 'pernas',
            str_contains($normalized, 'waist'), str_contains($normalized, 'abd') || str_contains($normalized, 'core') => 'core',
            str_contains($normalized, 'biceps'), str_contains($normalized, 'triceps'), str_contains($normalized, 'forearms'), str_contains($normalized, 'bra') => 'bracos',
            default => null,
        };
    }

    private function resolveTrainingDays(mixed $trainingFrequency): ?int
    {
        $normalized = trim((string) $trainingFrequency);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/(\d{1,2})/', $normalized, $matches) !== 1) {
            return null;
        }

        $days = (int) $matches[1];

        return $days > 0 ? $days : null;
    }

    private function resolveAge(User $user): ?int
    {
        if ($user->birth_date === null) {
            return null;
        }

        return Carbon::parse($user->birth_date)->age;
    }

    private function hasSevereInjury(string $injuries, string $restrictions): bool
    {
        $fullText = mb_strtolower(trim($injuries . ' ' . $restrictions));

        if ($fullText === '') {
            return false;
        }

        $keywords = [
            'grave',
            'fratura',
            'ruptura',
            'hernia',
            'cirurgia',
            'lesao severa',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($fullText, $keyword)) {
                return true;
            }
        }

        return false;
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
            $min = (int) $matches[1];
            $max = (int) $matches[2];

            return $min > 0 && $max >= $min;
        }

        if (preg_match('/^(\d{1,3})\s*(a|ate|até)\s*(\d{1,3})\s*(s|sec|secs|seg|segs|segundo|segundos|min|mins|minuto|minutos)$/i', $reps, $matches) === 1) {
            $min = (int) $matches[1];
            $max = (int) $matches[3];

            return $min > 0 && $max >= $min;
        }

        if (preg_match('/^(\d{1,3})\s*(s|sec|secs|seg|segs|segundo|segundos|min|mins|minuto|minutos|h|hr|hora|horas)\b.*$/i', $reps, $matches) === 1) {
            return (int) $matches[1] > 0;
        }

        if (preg_match('/^(\d{1,3})\s*(-|a|ate|até)\s*(\d{1,3})\s*(s|sec|secs|seg|segs|segundo|segundos|min|mins|minuto|minutos|h|hr|hora|horas)\b.*$/i', $reps, $matches) === 1) {
            $min = (int) $matches[1];
            $max = (int) $matches[3];

            return $min > 0 && $max >= $min;
        }

        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $reps, $matches) !== 1) {
            return false;
        }

        $min = (int) $matches[1];
        $max = (int) $matches[2];

        return $min > 0 && $max >= $min;
    }
}
