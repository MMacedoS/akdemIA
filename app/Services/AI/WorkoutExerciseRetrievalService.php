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
        if (! (bool) config('services.openai.vector_store.enabled', true)) {
            return $this->localFallback($context, 'disabled');
        }

        try {
            $sync = $this->vectorStoreService->ensureSynced($context->tenantId);
            $query = $this->buildQuery($context);
            $search = $this->client->searchVectorStore(
                $sync->vectorStoreId,
                $query,
                (int) config('services.openai.vector_store.max_search_results', 24),
            );

            $candidates = $this->parseSearchResults($search['body'] ?? []);

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
                'metadata' => ['candidate_count' => count($candidates)],
            ]);

            if (count($candidates) >= (int) config('services.openai.vector_store.minimum_candidates', 12)) {
                return new WorkoutRetrievalResult(
                    candidates: $candidates,
                    mode: 'vector_store',
                    query: $query,
                    vectorStoreId: $sync->vectorStoreId,
                    fileId: $sync->fileId,
                    metadata: ['source_hash' => $sync->sourceHash],
                );
            }
        } catch (Throwable) {
            return $this->localFallback($context, 'local_fallback');
        }

        return $this->localFallback($context, 'local_fallback');
    }

    private function buildQuery(WorkoutGenerationContext $context): string
    {
        return $context->retrievalQuery() . ' | diversidade semanal | exatamente 4 exercicios especificos e 1 cardio por dia | evitar hallucinations';
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

        $scored = collect($rows)
            ->map(function (array $row) use ($goal): array {
                $score = 0;
                $equipment = mb_strtolower((string) ($row['equipment'] ?? ''));
                $focus = mb_strtolower((string) ($row['focus'] ?? ''));

                if ($focus === 'cardio') {
                    $score += 20;
                }

                if (str_contains($goal, 'hipertrof') || str_contains($goal, 'massa')) {
                    if (preg_match('/(machine|barbell|cable|dumbbell)/i', $equipment) === 1) {
                        $score += 30;
                    }
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

        return new WorkoutRetrievalResult(
            candidates: array_values($selected),
            mode: $mode,
            query: $this->buildQuery($context),
            vectorStoreId: null,
            fileId: null,
            metadata: ['fallback' => true],
        );
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