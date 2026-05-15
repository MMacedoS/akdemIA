<?php

namespace App\Services\Workout\Planning;

use App\DTOs\AI\WorkoutExerciseCandidate;
use App\DTOs\AI\WorkoutGenerationContext;
use App\DTOs\AI\WorkoutRetrievalResult;
use App\Models\Workout\ExerciseMediaCache;

class ExerciseSelectionEngine
{
    public function select(
        WorkoutGenerationContext $context,
        WorkoutRetrievalResult $retrieval,
        array $splitPlan,
        array $volumeDistribution,
        array $fatiguePlan,
        array $trainingMemory,
    ): array {
        $candidatePool = $this->buildCandidatePool($retrieval);
        $previousExerciseIds = $this->previousExerciseIds($context);
        $overusedMovements = array_fill_keys($trainingMemory['overused_movements'] ?? [], true);
        $selectionBiases = $this->selectionBiases($context, $trainingMemory);
        $selectedDays = [];
        $usedAcrossWeek = [];
        $previousDayExerciseIds = [];

        foreach ($splitPlan['split'] as $day) {
            $sessionPatterns = [];
            $sessionEquipment = [];
            $sessionExerciseIds = [];
            $specificExercises = [];

            foreach ($day['preferred_order'] as $pattern) {
                if (count($specificExercises) >= 4) {
                    break;
                }

                $candidate = $this->pickBestCandidate(
                    $candidatePool,
                    $day['allowed_focus_tokens'],
                    $pattern,
                    $usedAcrossWeek,
                    $previousDayExerciseIds,
                    $previousExerciseIds,
                    $overusedMovements,
                    $sessionPatterns,
                    $sessionEquipment,
                    $sessionExerciseIds,
                    $selectionBiases,
                    (int) ($day['max_same_pattern_per_session'] ?? 2),
                );

                if ($candidate === null) {
                    continue;
                }

                $exercise = $this->buildExercisePrescription(
                    $candidate,
                    'specific',
                    $pattern,
                    $volumeDistribution,
                    $day['allowed_focus_tokens'],
                );

                $specificExercises[] = $exercise;
                $sessionExerciseIds[$candidate->remoteExerciseId] = true;
                $usedAcrossWeek[$candidate->remoteExerciseId] = ($usedAcrossWeek[$candidate->remoteExerciseId] ?? 0) + 1;
                $sessionPatterns[] = $pattern;
                $sessionEquipment[$candidate->equipment] = true;
            }

            if (count($specificExercises) < 4) {
                foreach ($candidatePool as $candidate) {
                    if (count($specificExercises) >= 4) {
                        break;
                    }

                    if (! $this->candidateMatchesDay($candidate, $day['allowed_focus_tokens'])) {
                        continue;
                    }

                    if (
                        isset($usedAcrossWeek[$candidate->remoteExerciseId])
                        || isset($sessionExerciseIds[$candidate->remoteExerciseId])
                        || isset($previousDayExerciseIds[$candidate->remoteExerciseId])
                    ) {
                        continue;
                    }

                    $patterns = $this->inferPatterns($candidate);

                    if (($selectionBiases['shoulder_sensitive'] ?? false) === true
                        && isset($previousExerciseIds[$candidate->remoteExerciseId])
                        && in_array('horizontal_push', $patterns, true)
                        && $candidate->equipment === 'barbell'
                    ) {
                        continue;
                    }

                    $pattern = $patterns[0] ?? 'bilateral';
                    $specificExercises[] = $this->buildExercisePrescription(
                        $candidate,
                        'specific',
                        $pattern,
                        $volumeDistribution,
                        $day['allowed_focus_tokens'],
                    );
                    $sessionExerciseIds[$candidate->remoteExerciseId] = true;
                    $usedAcrossWeek[$candidate->remoteExerciseId] = ($usedAcrossWeek[$candidate->remoteExerciseId] ?? 0) + 1;
                }
            }

            $cardioCandidate = $this->pickCardioCandidate($candidatePool, $usedAcrossWeek, $previousDayExerciseIds, $previousExerciseIds);
            $cardioExercise = $this->buildCardioPrescription($cardioCandidate);

            if ($cardioCandidate !== null) {
                $usedAcrossWeek[$cardioCandidate->remoteExerciseId] = ($usedAcrossWeek[$cardioCandidate->remoteExerciseId] ?? 0) + 1;
            }

            $selectedDay = array_merge($day, [
                'focus' => $day['focus_label'],
                'selected_exercises' => array_merge($specificExercises, [$cardioExercise]),
                'volume_target' => $this->resolveVolumeTarget($day['allowed_focus_tokens'], $volumeDistribution),
                'fatigue_constraints' => [
                    'max_heavy_compounds' => $fatiguePlan['max_heavy_compounds_per_session'] ?? 2,
                    'cardio_position' => $fatiguePlan['cardio_position'] ?? 'last',
                ],
            ]);

            $selectedDays[] = $selectedDay;
            $previousDayExerciseIds = $this->selectedExerciseIds($selectedDay['selected_exercises']);
        }

        return $selectedDays;
    }

    /**
     * @return array<int, WorkoutExerciseCandidate>
     */
    private function buildCandidatePool(WorkoutRetrievalResult $retrieval): array
    {
        $pool = [];

        foreach ($retrieval->candidates as $candidate) {
            $pool[$candidate->remoteExerciseId] = $candidate;
        }

        $catalogCandidates = ExerciseMediaCache::query()->get()->map(function (ExerciseMediaCache $exercise): WorkoutExerciseCandidate {
            $payload = is_array($exercise->payload) ? $exercise->payload : [];

            return new WorkoutExerciseCandidate(
                remoteExerciseId: (string) $exercise->remote_exercise_id,
                localizedNamePtBr: (string) ($exercise->localized_name_pt_br ?: $payload['name'] ?? $exercise->workoutx_name),
                workoutxName: (string) $exercise->workoutx_name,
                focus: $this->normalizeFocusToken((string) ($payload['bodyPart'] ?? $payload['target'] ?? '')) ?? 'geral',
                bodyPart: (string) ($payload['bodyPart'] ?? ''),
                target: (string) ($payload['target'] ?? ''),
                equipment: (string) ($payload['equipment'] ?? ''),
            );
        });

        foreach ($catalogCandidates as $candidate) {
            if (! isset($pool[$candidate->remoteExerciseId])) {
                $pool[$candidate->remoteExerciseId] = $candidate;
            }
        }

        return array_values($pool);
    }

    /**
     * @param  array<int, WorkoutExerciseCandidate>  $candidatePool
     * @param  array<string, int>  $usedAcrossWeek
     * @param  array<string, true>  $previousDayExerciseIds
     * @param  array<string, true>  $previousExerciseIds
     * @param  array<string, true>  $overusedMovements
     * @param  array<int, string>  $sessionPatterns
     * @param  array<string, true>  $sessionEquipment
     * @param  array<string, true>  $sessionExerciseIds
     * @param  array<string, bool>  $selectionBiases
     */
    private function pickBestCandidate(
        array $candidatePool,
        array $allowedFocusTokens,
        string $desiredPattern,
        array $usedAcrossWeek,
        array $previousDayExerciseIds,
        array $previousExerciseIds,
        array $overusedMovements,
        array $sessionPatterns,
        array $sessionEquipment,
        array $sessionExerciseIds,
        array $selectionBiases,
        int $maxSamePatternPerSession,
    ): ?WorkoutExerciseCandidate {
        $scored = [];

        foreach ($candidatePool as $candidate) {
            if (! $this->candidateMatchesDay($candidate, $allowedFocusTokens)) {
                continue;
            }

            $patterns = $this->inferPatterns($candidate);

            if (($selectionBiases['shoulder_sensitive'] ?? false) === true
                && $desiredPattern === 'horizontal_push'
                && in_array('horizontal_push', $patterns, true)
                && isset($previousExerciseIds[$candidate->remoteExerciseId])
                && $candidate->equipment === 'barbell'
            ) {
                continue;
            }

            if (isset($sessionExerciseIds[$candidate->remoteExerciseId]) || isset($previousDayExerciseIds[$candidate->remoteExerciseId])) {
                continue;
            }

            if (isset($usedAcrossWeek[$candidate->remoteExerciseId])) {
                continue;
            }

            $score = 0;
            $samePatternCount = count(array_filter($sessionPatterns, static fn(string $pattern): bool => $pattern === $desiredPattern));

            if (in_array($desiredPattern, $patterns, true)) {
                $score += 40;
            }

            if (! isset($previousExerciseIds[$candidate->remoteExerciseId])) {
                $score += 18;
            }

            if (! isset($overusedMovements[$candidate->remoteExerciseId])) {
                $score += 12;
            } else {
                $score -= 24;
            }

            if (! isset($sessionEquipment[$candidate->equipment])) {
                $score += 6;
            }

            $score += 10;

            if (($selectionBiases['shoulder_sensitive'] ?? false) === true) {
                if ($desiredPattern === 'horizontal_push' && in_array('horizontal_push', $patterns, true)) {
                    $score += match ($candidate->equipment) {
                        'machine', 'cable' => 12,
                        'dumbbell' => 6,
                        'barbell' => -18,
                        default => 0,
                    };
                }

                if (in_array('vertical_push', $patterns, true)) {
                    $score -= 12;
                }
            }

            if (($selectionBiases['prefer_vertical_pull'] ?? false) === true && in_array('vertical_pull', $patterns, true)) {
                $score += $desiredPattern === 'vertical_pull' ? 16 : 6;
            }

            if ($samePatternCount >= $maxSamePatternPerSession && in_array($desiredPattern, $patterns, true)) {
                $score -= 20;
            }

            $score += match ($candidate->equipment) {
                'machine', 'cable', 'barbell', 'dumbbell' => 4,
                default => 0,
            };

            $scored[] = ['candidate' => $candidate, 'score' => $score];
        }

        usort($scored, static fn(array $left, array $right): int => $right['score'] <=> $left['score']);

        return $scored[0]['candidate'] ?? null;
    }

    /**
     * @return array<string, bool>
     */
    private function selectionBiases(WorkoutGenerationContext $context, array $trainingMemory): array
    {
        $imbalanceFlags = $trainingMemory['imbalance_flags'] ?? [];
        $injuryRestrictions = mb_strtolower(trim(
            (string) ($context->profile['injuries'] ?? '') . ' ' . (string) ($context->profile['restrictions'] ?? '')
        ));

        return [
            'shoulder_sensitive' => ($imbalanceFlags['shoulder_sensitive'] ?? false) === true
                || str_contains($injuryRestrictions, 'ombro')
                || str_contains($injuryRestrictions, 'shoulder'),
            'prefer_vertical_pull' => ($imbalanceFlags['vertical_pull_deficit'] ?? false) === true,
        ];
    }

    /**
     * @param  array<int, WorkoutExerciseCandidate>  $candidatePool
     * @param  array<string, int>  $usedAcrossWeek
     * @param  array<string, true>  $previousDayExerciseIds
     * @param  array<string, true>  $previousExerciseIds
     */
    private function pickCardioCandidate(array $candidatePool, array $usedAcrossWeek, array $previousDayExerciseIds, array $previousExerciseIds): ?WorkoutExerciseCandidate
    {
        $cardioCandidates = array_values(array_filter($candidatePool, function (WorkoutExerciseCandidate $candidate) use ($previousDayExerciseIds): bool {
            return $this->normalizeFocusToken($candidate->focus) === 'cardio'
                && ! isset($previousDayExerciseIds[$candidate->remoteExerciseId]);
        }));

        usort($cardioCandidates, function (WorkoutExerciseCandidate $left, WorkoutExerciseCandidate $right) use ($usedAcrossWeek, $previousExerciseIds): int {
            $leftUsage = (int) ($usedAcrossWeek[$left->remoteExerciseId] ?? 0);
            $rightUsage = (int) ($usedAcrossWeek[$right->remoteExerciseId] ?? 0);

            if ($leftUsage !== $rightUsage) {
                return $leftUsage <=> $rightUsage;
            }

            $leftRepeated = isset($previousExerciseIds[$left->remoteExerciseId]) ? 1 : 0;
            $rightRepeated = isset($previousExerciseIds[$right->remoteExerciseId]) ? 1 : 0;

            return $leftRepeated <=> $rightRepeated;
        });

        return $cardioCandidates[0] ?? null;
    }

    private function candidateMatchesDay(WorkoutExerciseCandidate $candidate, array $allowedFocusTokens): bool
    {
        $focusToken = $this->normalizeFocusToken($candidate->focus)
            ?? $this->normalizeFocusToken($candidate->bodyPart)
            ?? $this->normalizeFocusToken($candidate->target);

        return $focusToken !== null && in_array($focusToken, $allowedFocusTokens, true);
    }

    private function buildExercisePrescription(
        WorkoutExerciseCandidate $candidate,
        string $category,
        string $pattern,
        array $volumeDistribution,
        array $allowedFocusTokens,
    ): array {
        $focusToken = $this->normalizeFocusToken($candidate->focus)
            ?? $this->normalizeFocusToken($candidate->bodyPart)
            ?? $allowedFocusTokens[0]
            ?? 'geral';
        $volume = $volumeDistribution[$focusToken]['sets_per_session'] ?? 4;
        $sets = max(2, min(5, (int) round($volume / 3)));

        return [
            'exercise_id' => $candidate->remoteExerciseId,
            'remote_exercise_id' => $candidate->remoteExerciseId,
            'name' => $candidate->localizedNamePtBr,
            'workoutx_name' => $candidate->workoutxName,
            'category' => $category,
            'focus_token' => $focusToken,
            'reason' => 'balanced_' . $pattern,
            'patterns' => $this->inferPatterns($candidate),
            'sets' => $sets,
            'reps' => $this->defaultRepsForPattern($pattern),
            'rest' => $this->defaultRestForPattern($pattern),
            'notes' => $this->defaultNotesForPattern($pattern),
            'steps' => $this->defaultStepsForPattern($pattern),
            'body_part' => $candidate->bodyPart,
            'target' => $candidate->target,
            'equipment' => $candidate->equipment,
        ];
    }

    private function buildCardioPrescription(?WorkoutExerciseCandidate $candidate): array
    {
        if ($candidate === null) {
            return [
                'exercise_id' => 'cardio-default',
                'remote_exercise_id' => 'cardio-default',
                'name' => 'Caminhada moderada',
                'workoutx_name' => 'moderate-walk',
                'category' => 'cardio',
                'focus_token' => 'cardio',
                'reason' => 'fatigue_managed_cardio',
                'patterns' => ['cardio'],
                'sets' => 1,
                'reps' => '12-20 min',
                'rest' => '0s',
                'notes' => 'Mantenha o ritmo confortavel e respiracao controlada.',
                'steps' => ['Inicie em ritmo leve', 'Aumente gradualmente a intensidade', 'Finalize reduzindo o ritmo'],
                'body_part' => 'cardio',
                'target' => 'cardiovascular system',
                'equipment' => 'body weight',
            ];
        }

        return [
            'exercise_id' => $candidate->remoteExerciseId,
            'remote_exercise_id' => $candidate->remoteExerciseId,
            'name' => $candidate->localizedNamePtBr,
            'workoutx_name' => $candidate->workoutxName,
            'category' => 'cardio',
            'focus_token' => 'cardio',
            'reason' => 'fatigue_managed_cardio',
            'patterns' => ['cardio'],
            'sets' => 1,
            'reps' => '12-20 min',
            'rest' => '0s',
            'notes' => 'Use intensidade moderada e mantenha a tecnica estavel.',
            'steps' => ['Inicie em ritmo leve', 'Mantenha constancia durante o bloco principal', 'Finalize com desaceleracao gradual'],
            'body_part' => $candidate->bodyPart,
            'target' => $candidate->target,
            'equipment' => $candidate->equipment,
        ];
    }

    private function resolveVolumeTarget(array $allowedFocusTokens, array $volumeDistribution): array
    {
        $targets = [];

        foreach ($allowedFocusTokens as $focusToken) {
            if (isset($volumeDistribution[$focusToken])) {
                $targets[$focusToken] = $volumeDistribution[$focusToken]['sets_per_session'];
            }
        }

        return $targets;
    }

    /**
     * @return array<string, true>
     */
    private function previousExerciseIds(WorkoutGenerationContext $context): array
    {
        $ids = [];

        foreach (($context->previousWorkoutPlan['weekly_plan'] ?? []) as $day) {
            foreach (($day['exercises'] ?? []) as $exercise) {
                $remoteExerciseId = trim((string) ($exercise['remote_exercise_id'] ?? ''));

                if ($remoteExerciseId !== '') {
                    $ids[$remoteExerciseId] = true;
                }
            }
        }

        return $ids;
    }

    /**
     * @param  array<int, array<string, mixed>>  $selectedExercises
     * @return array<string, true>
     */
    private function selectedExerciseIds(array $selectedExercises): array
    {
        $ids = [];

        foreach ($selectedExercises as $exercise) {
            $remoteExerciseId = trim((string) ($exercise['remote_exercise_id'] ?? ''));

            if ($remoteExerciseId !== '') {
                $ids[$remoteExerciseId] = true;
            }
        }

        return $ids;
    }

    /**
     * @return array<int, string>
     */
    private function inferPatterns(WorkoutExerciseCandidate $candidate): array
    {
        $name = mb_strtolower($candidate->workoutxName . ' ' . $candidate->localizedNamePtBr);

        $patterns = [];

        if (str_contains($name, 'deadlift') || str_contains($name, 'romanian') || str_contains($name, 'good morning')) {
            $patterns[] = 'hinge';
        }

        if (str_contains($name, 'squat') || str_contains($name, 'leg press')) {
            $patterns[] = 'squat';
        }

        if (str_contains($name, 'lunge') || str_contains($name, 'split squat') || str_contains($name, 'step up')) {
            $patterns[] = 'lunge';
            $patterns[] = 'unilateral';
        }

        if (str_contains($name, 'pull up') || str_contains($name, 'pulldown')) {
            $patterns[] = 'vertical_pull';
        }

        if (str_contains($name, 'row')) {
            $patterns[] = 'horizontal_pull';
        }

        if (str_contains($name, 'shoulder press') || str_contains($name, 'overhead') || str_contains($name, 'arnold')) {
            $patterns[] = 'vertical_push';
        }

        if (str_contains($name, 'bench') || str_contains($name, 'push up') || str_contains($name, 'fly') || str_contains($name, 'chest press')) {
            $patterns[] = 'horizontal_push';
        }

        if ($patterns === []) {
            $focusToken = $this->normalizeFocusToken($candidate->focus)
                ?? $this->normalizeFocusToken($candidate->bodyPart)
                ?? $this->normalizeFocusToken($candidate->target);

            $patterns = match ($focusToken) {
                'peito' => ['horizontal_push'],
                'costas' => ['horizontal_pull'],
                'ombro' => ['vertical_push'],
                'pernas' => ['bilateral'],
                'core' => ['rotation'],
                default => ['bilateral'],
            };
        }

        return array_values(array_unique($patterns));
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
            str_contains($normalized, 'back'), str_contains($normalized, 'costa'), str_contains($normalized, 'lat') => 'costas',
            str_contains($normalized, 'shoulder'), str_contains($normalized, 'ombro') => 'ombro',
            str_contains($normalized, 'leg'), str_contains($normalized, 'glute'), str_contains($normalized, 'quadr'), str_contains($normalized, 'hamstring'), str_contains($normalized, 'perna') => 'pernas',
            str_contains($normalized, 'waist'), str_contains($normalized, 'abd'), str_contains($normalized, 'core') => 'core',
            str_contains($normalized, 'biceps'), str_contains($normalized, 'triceps'), str_contains($normalized, 'arm'), str_contains($normalized, 'bra') => 'bracos',
            default => null,
        };
    }

    private function defaultRepsForPattern(string $pattern): string
    {
        return match ($pattern) {
            'hinge', 'squat', 'horizontal_push', 'horizontal_pull', 'vertical_push', 'vertical_pull' => '8-12',
            'lunge', 'rotation', 'carry', 'unilateral' => '10-14',
            default => '10-12',
        };
    }

    private function defaultRestForPattern(string $pattern): string
    {
        return match ($pattern) {
            'hinge', 'squat' => '75s',
            'horizontal_push', 'horizontal_pull', 'vertical_push', 'vertical_pull' => '60s',
            default => '45s',
        };
    }

    private function defaultNotesForPattern(string $pattern): string
    {
        return match ($pattern) {
            'hinge', 'squat' => 'Priorize tecnica, estabilidade e amplitude segura.',
            'horizontal_push', 'vertical_push' => 'Controle a fase de descida e mantenha estabilidade escapular.',
            'horizontal_pull', 'vertical_pull' => 'Inicie o movimento pelas escapulas e evite compensacoes lombares.',
            default => 'Execute de forma controlada e mantenha a respiracao ritmada.',
        };
    }

    private function defaultStepsForPattern(string $pattern): array
    {
        return match ($pattern) {
            'hinge' => ['Ajuste a base com estabilidade', 'Inicie o quadril para tras mantendo coluna neutra', 'Retorne com controle sem perder alinhamento'],
            'squat' => ['Posicione os pes em base estavel', 'Desca com controle mantendo joelhos alinhados', 'Suba empurrando o solo de forma uniforme'],
            'horizontal_push', 'vertical_push' => ['Ajuste a postura e a pegada', 'Controle a fase excêntrica', 'Empurre mantendo estabilidade e amplitude segura'],
            'horizontal_pull', 'vertical_pull' => ['Organize a postura inicial', 'Puxe ativando costas e bracos sem impulso', 'Retorne controlando a carga'],
            default => ['Prepare a postura inicial', 'Execute o movimento de forma controlada', 'Finalize retornando sem perder a tecnica'],
        };
    }
}
