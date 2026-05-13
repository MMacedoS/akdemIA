<?php

namespace App\Services\AI;

use App\DTOs\AI\WorkoutExerciseCandidate;
use App\DTOs\AI\WorkoutGenerationContext;
use App\DTOs\AI\WorkoutRetrievalResult;
use App\Services\Workouts\ExerciseCatalogService;
use Throwable;

class WorkoutExerciseRetrievalService
{
    public function __construct(
        private readonly WorkoutCatalogVectorStoreService $vectorStoreService,
        private readonly OpenAIResponsesClient $client,
        private readonly ExerciseCatalogService $exerciseCatalogService,
        private readonly AiRequestLogger $logger,
    ) {}

    public function retrieve(WorkoutGenerationContext $context): WorkoutRetrievalResult
    {
        $previousExerciseIds = $this->previousExerciseIds($context);

        if (! (bool) config('services.openai.vector_store.enabled', true)) {
            return $this->localFallback($context, 'disabled');
        }

        try {
            $sync = $this->vectorStoreService->ensureSynced($context->tenantId);
            $query = $this->buildQuery($context);
            $searchResultLimit = max(24, (int) config('services.openai.vector_store.max_search_results', 120));
            $search = $this->client->searchVectorStore(
                $sync->vectorStoreId,
                $query,
                $searchResultLimit,
            );

            $candidates = $this->prioritizeCandidates(
                $this->parseSearchResults($search['body'] ?? []),
                $previousExerciseIds,
            );
            $candidates = $this->mergeCatalogAlternatives($context, $candidates, $previousExerciseIds);
            $candidates = $this->balanceCandidates($candidates, $previousExerciseIds);
            $focusCounts = $this->countCandidatesByFocus($candidates);
            $repetitionStats = $this->candidateRepetitionStats($candidates, $previousExerciseIds);

            $this->logger->log([
                'tenant_id' => $context->tenantId,
                'user_id' => $context->userId,
                'type' => 'workout',
                'operation' => 'retrieval',
                'http_status' => $search['status'] ?? 200,
                'latency_ms' => $search['latency_ms'] ?? null,
                'retrieval_mode' => 'vector_store',
                'vector_store_id' => $sync->vectorStoreId,
                'file_id' => $sync->fileId,
                'request_payload' => ['query' => $query],
                'response_payload' => ['matches' => $search['body']['data'] ?? []],
                'metadata' => [
                    'candidate_count' => count($candidates),
                    'candidate_focus_counts' => $focusCounts,
                    'candidate_repetition_stats' => $repetitionStats,
                ],
            ]);

            if (count($candidates) >= (int) config('services.openai.vector_store.minimum_candidates', 12)) {
                return new WorkoutRetrievalResult(
                    candidates: $candidates,
                    mode: 'vector_store',
                    query: $query,
                    vectorStoreId: $sync->vectorStoreId,
                    fileId: $sync->fileId,
                    metadata: [
                        'source_hash' => $sync->sourceHash,
                        'candidate_focus_counts' => $focusCounts,
                        'candidate_repetition_stats' => $repetitionStats,
                    ],
                );
            }
        } catch (Throwable) {
            return $this->localFallback($context, 'local_fallback');
        }

        return $this->localFallback($context, 'local_fallback');
    }

    private function buildQuery(WorkoutGenerationContext $context): string
    {
        $parts = [
            $context->retrievalQuery(),
            'diversidade semanal',
            'variacao entre geracoes',
            'exatamente 4 exercicios especificos e 1 cardio por dia',
            'evitar hallucinations',
        ];

        $previousExerciseNames = $this->previousExerciseNames($context);

        if ($previousExerciseNames !== []) {
            $parts[] = 'evitar repetir exercicios anteriores quando houver alternativa: ' . implode(', ', array_slice($previousExerciseNames, 0, 12));
        }

        return implode(' | ', $parts);
    }

    /**
     * @return array<int, WorkoutExerciseCandidate>
     */
    private function parseSearchResults(array $body): array
    {
        $candidates = [];

        foreach (($body['data'] ?? []) as $match) {
            foreach (($match['content'] ?? []) as $content) {
                $text = trim((string) ($content['text'] ?? ''));

                if ($text === '') {
                    continue;
                }

                foreach (preg_split('/\R+/', $text) ?: [] as $line) {
                    $decoded = json_decode(trim($line), true);

                    if (! is_array($decoded)) {
                        continue;
                    }

                    $candidate = $this->candidateFromArray($decoded);

                    if ($candidate instanceof WorkoutExerciseCandidate) {
                        $candidates[$candidate->remoteExerciseId] = $candidate;
                    }
                }
            }
        }

        return array_values($candidates);
    }

    private function localFallback(WorkoutGenerationContext $context, string $mode): WorkoutRetrievalResult
    {
        $goal = mb_strtolower((string) ($context->profile['goal'] ?? ''));
        $rows = $this->exerciseCatalogService->buildVectorStoreCatalogRows();

        $previousExerciseIds = $this->previousExerciseIds($context);

        $scored = collect($rows)
            ->map(function (array $row) use ($goal, $previousExerciseIds): array {
                $score = 0;
                $equipment = mb_strtolower((string) ($row['equipment'] ?? ''));
                $focus = mb_strtolower((string) ($row['focus'] ?? ''));
                $remoteExerciseId = trim((string) ($row['remote_exercise_id'] ?? $row['id'] ?? ''));

                if ($focus === 'cardio') {
                    $score += 20;
                }

                if (str_contains($goal, 'hipertrof') || str_contains($goal, 'massa')) {
                    if (preg_match('/(machine|barbell|cable|dumbbell)/i', $equipment) === 1) {
                        $score += 30;
                    }
                }

                if ($remoteExerciseId !== '' && isset($previousExerciseIds[$remoteExerciseId])) {
                    $score -= 80;
                }

                return ['row' => $row, 'score' => $score];
            })
            ->sortByDesc('score')
            ->values();

        $selected = [];
        $perFocus = [];

        foreach ($scored as $item) {
            $row = $item['row'];
            $focus = (string) ($row['focus'] ?? 'geral');
            $limit = $focus === 'cardio' ? 6 : 4;

            if (($perFocus[$focus] ?? 0) >= $limit) {
                continue;
            }

            $candidate = $this->candidateFromArray($row);

            if ($candidate === null) {
                continue;
            }

            $selected[$candidate->remoteExerciseId] = $candidate;
            $perFocus[$focus] = ($perFocus[$focus] ?? 0) + 1;

            if (count($selected) >= 24) {
                break;
            }
        }

        $candidates = $this->balanceCandidates(
            $this->prioritizeCandidates(array_values($selected), $previousExerciseIds),
            $previousExerciseIds,
        );

        return new WorkoutRetrievalResult(
            candidates: $candidates,
            mode: $mode,
            query: $this->buildQuery($context),
            vectorStoreId: null,
            fileId: null,
            metadata: [
                'fallback' => true,
                'candidate_focus_counts' => $this->countCandidatesByFocus($candidates),
                'candidate_repetition_stats' => $this->candidateRepetitionStats($candidates, $previousExerciseIds),
            ],
        );
    }

    /**
     * @param  array<int, WorkoutExerciseCandidate>  $candidates
     * @param  array<string, true>  $previousExerciseIds
     * @return array<int, WorkoutExerciseCandidate>
     */
    private function prioritizeCandidates(array $candidates, array $previousExerciseIds): array
    {
        usort($candidates, function (WorkoutExerciseCandidate $left, WorkoutExerciseCandidate $right) use ($previousExerciseIds): int {
            $leftRepeated = isset($previousExerciseIds[$left->remoteExerciseId]) ? 1 : 0;
            $rightRepeated = isset($previousExerciseIds[$right->remoteExerciseId]) ? 1 : 0;

            if ($leftRepeated !== $rightRepeated) {
                return $leftRepeated <=> $rightRepeated;
            }

            return strcmp($left->remoteExerciseId, $right->remoteExerciseId);
        });

        return $candidates;
    }

    /**
     * @param  array<int, WorkoutExerciseCandidate>  $vectorCandidates
     * @param  array<string, true>  $previousExerciseIds
     * @return array<int, WorkoutExerciseCandidate>
     */
    private function mergeCatalogAlternatives(WorkoutGenerationContext $context, array $vectorCandidates, array $previousExerciseIds): array
    {
        $selected = [];
        $targetPoolSize = $this->targetCandidatePoolSize();

        foreach ($vectorCandidates as $candidate) {
            $selected[$candidate->remoteExerciseId] = $candidate;
        }

        if (count($selected) >= $targetPoolSize) {
            return array_values($selected);
        }

        $goal = mb_strtolower((string) ($context->profile['goal'] ?? ''));
        $rows = $this->exerciseCatalogService->buildVectorStoreCatalogRows();

        $alternatives = collect($rows)
            ->map(function (array $row) use ($goal, $previousExerciseIds): array {
                $remoteExerciseId = trim((string) ($row['remote_exercise_id'] ?? $row['id'] ?? ''));
                $equipment = mb_strtolower((string) ($row['equipment'] ?? ''));
                $focus = mb_strtolower((string) ($row['focus'] ?? ''));
                $score = 0;

                if ($focus === 'cardio') {
                    $score += 20;
                }

                if (str_contains($goal, 'hipertrof') || str_contains($goal, 'massa') || str_contains($goal, 'muscul')) {
                    if (preg_match('/(machine|barbell|cable|dumbbell|smith|lever|kettlebell)/i', $equipment) === 1) {
                        $score += 30;
                    }
                }

                if ($remoteExerciseId !== '' && isset($previousExerciseIds[$remoteExerciseId])) {
                    $score -= 80;
                }

                return ['row' => $row, 'score' => $score];
            })
            ->sortByDesc('score')
            ->values();

        foreach ($alternatives as $item) {
            $candidate = $this->candidateFromArray($item['row']);

            if (! $candidate instanceof WorkoutExerciseCandidate) {
                continue;
            }

            if (isset($selected[$candidate->remoteExerciseId])) {
                continue;
            }

            $selected[$candidate->remoteExerciseId] = $candidate;

            if (count($selected) >= $targetPoolSize) {
                break;
            }
        }

        return $this->prioritizeCandidates(array_values($selected), $previousExerciseIds);
    }

    /**
     * @param  array<int, WorkoutExerciseCandidate>  $candidates
     * @param  array<string, true>  $previousExerciseIds
     * @return array<int, WorkoutExerciseCandidate>
     */
    private function balanceCandidates(array $candidates, array $previousExerciseIds): array
    {
        if ($candidates === []) {
            return [];
        }

        $grouped = [];

        foreach ($candidates as $candidate) {
            $focus = $this->normalizeFocus($candidate->focus);
            $grouped[$focus] ??= [];
            $grouped[$focus][] = $candidate;
        }

        foreach ($grouped as $focus => $focusCandidates) {
            $grouped[$focus] = $this->shuffleCandidatesWithinFocus($focusCandidates, $previousExerciseIds);
        }

        $orderedFocuses = array_keys($grouped);
        usort($orderedFocuses, function (string $left, string $right): int {
            if ($left === 'cardio') {
                return 1;
            }

            if ($right === 'cardio') {
                return -1;
            }

            return strcmp($left, $right);
        });

        $balanced = [];
        $targetPoolSize = min($this->targetCandidatePoolSize(), count($candidates));

        while (count($balanced) < $targetPoolSize) {
            $pickedInCycle = false;

            foreach ($orderedFocuses as $focus) {
                $candidate = array_shift($grouped[$focus]);

                if (! $candidate instanceof WorkoutExerciseCandidate) {
                    continue;
                }

                $balanced[] = $candidate;
                $pickedInCycle = true;

                if (count($balanced) >= $targetPoolSize) {
                    break 2;
                }
            }

            if (! $pickedInCycle) {
                break;
            }
        }

        return $balanced;
    }

    /**
     * @param  array<int, WorkoutExerciseCandidate>  $candidates
     * @return array<string, int>
     */
    private function countCandidatesByFocus(array $candidates): array
    {
        $counts = [];

        foreach ($candidates as $candidate) {
            $focus = $this->normalizeFocus($candidate->focus);
            $counts[$focus] = ($counts[$focus] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param  array<int, WorkoutExerciseCandidate>  $candidates
     * @param  array<string, true>  $previousExerciseIds
     * @return array<string, int>
     */
    private function candidateRepetitionStats(array $candidates, array $previousExerciseIds): array
    {
        $repeated = 0;

        foreach ($candidates as $candidate) {
            if (isset($previousExerciseIds[$candidate->remoteExerciseId])) {
                $repeated++;
            }
        }

        return [
            'fresh' => count($candidates) - $repeated,
            'repeated' => $repeated,
        ];
    }

    /**
     * @param  array<int, WorkoutExerciseCandidate>  $candidates
     * @param  array<string, true>  $previousExerciseIds
     * @return array<int, WorkoutExerciseCandidate>
     */
    private function shuffleCandidatesWithinFocus(array $candidates, array $previousExerciseIds): array
    {
        $fresh = [];
        $repeated = [];

        foreach ($candidates as $candidate) {
            if (isset($previousExerciseIds[$candidate->remoteExerciseId])) {
                $repeated[] = $candidate;
                continue;
            }

            $fresh[] = $candidate;
        }

        shuffle($fresh);
        shuffle($repeated);

        return array_values(array_merge($fresh, $repeated));
    }

    private function targetCandidatePoolSize(): int
    {
        return max(48, min((int) config('services.openai.vector_store.max_search_results', 120), 180));
    }

    private function normalizeFocus(string $focus): string
    {
        $normalized = trim(mb_strtolower($focus));

        return $normalized !== '' ? $normalized : 'geral';
    }

    /**
     * @return array<string, true>
     */
    private function previousExerciseIds(WorkoutGenerationContext $context): array
    {
        $weeklyPlan = is_array($context->previousWorkoutPlan['weekly_plan'] ?? null)
            ? $context->previousWorkoutPlan['weekly_plan']
            : [];

        return collect($weeklyPlan)
            ->flatMap(fn(mixed $day): array => is_array(data_get($day, 'exercises')) ? data_get($day, 'exercises') : [])
            ->map(fn(mixed $exercise): string => trim((string) data_get($exercise, 'remote_exercise_id', '')))
            ->filter()
            ->unique()
            ->mapWithKeys(fn(string $remoteExerciseId): array => [$remoteExerciseId => true])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function previousExerciseNames(WorkoutGenerationContext $context): array
    {
        $weeklyPlan = is_array($context->previousWorkoutPlan['weekly_plan'] ?? null)
            ? $context->previousWorkoutPlan['weekly_plan']
            : [];

        return collect($weeklyPlan)
            ->flatMap(fn(mixed $day): array => is_array(data_get($day, 'exercises')) ? data_get($day, 'exercises') : [])
            ->map(fn(mixed $exercise): string => trim((string) data_get($exercise, 'name', '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function candidateFromArray(array $row): ?WorkoutExerciseCandidate
    {
        $remoteExerciseId = trim((string) ($row['remote_exercise_id'] ?? $row['id'] ?? ''));

        if ($remoteExerciseId === '') {
            return null;
        }

        return new WorkoutExerciseCandidate(
            remoteExerciseId: $remoteExerciseId,
            localizedNamePtBr: trim((string) ($row['localized_name_pt_br'] ?? $row['name'] ?? '')),
            workoutxName: trim((string) ($row['workoutx_name'] ?? '')),
            focus: trim((string) ($row['focus'] ?? 'geral')),
            bodyPart: trim((string) ($row['body_part'] ?? '')),
            target: trim((string) ($row['target'] ?? '')),
            equipment: trim((string) ($row['equipment'] ?? '')),
        );
    }
}
