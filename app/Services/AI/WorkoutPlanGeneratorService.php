<?php

namespace App\Services\AI;

use App\DTOs\AI\StructuredResponseResult;
use App\DTOs\AI\WorkoutGenerationContext;
use App\DTOs\AI\WorkoutRetrievalResult;

class WorkoutPlanGeneratorService
{
    public function __construct(
        private readonly OpenAIResponsesClient $client,
        private readonly AiResponseCacheService $cache,
        private readonly AiRequestLogger $logger,
    ) {}

    public function generate(WorkoutGenerationContext $context, WorkoutRetrievalResult $retrieval): StructuredResponseResult
    {
        $payload = $this->buildPayload($context, $retrieval);
        $cacheKey = $this->cache->buildKey('workout_generation', [
            'prompt_version' => AiService::WORKOUT_PROMPT_VERSION,
            'context' => $context->promptFingerprint(),
            'retrieval_mode' => $retrieval->mode,
            'vector_store_id' => $retrieval->vectorStoreId,
            'candidate_ids' => array_map(static fn(array $candidate): string => (string) ($candidate['remote_exercise_id'] ?? ''), $retrieval->compactCandidates()),
        ]);

        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return new StructuredResponseResult(
                data: $cached['data'] ?? [],
                rawResponse: $cached['raw_response'] ?? [],
                cacheHit: true,
                cacheKey: $cacheKey,
                model: $cached['model'] ?? (string) config('services.openai.responses_model', 'gpt-4o-mini'),
                latencyMs: null,
                usage: $cached['usage'] ?? [],
                responseId: $cached['response_id'] ?? null,
            );
        }

        $response = $this->client->createResponse($payload);
        $decoded = $this->client->decodeStructuredOutput($response['body'] ?? []);

        $result = new StructuredResponseResult(
            data: $decoded,
            rawResponse: $response['body'] ?? [],
            cacheHit: false,
            cacheKey: $cacheKey,
            model: (string) data_get($response, 'body.model', config('services.openai.responses_model', 'gpt-4o-mini')),
            latencyMs: $response['latency_ms'] ?? null,
            usage: is_array(data_get($response, 'body.usage')) ? data_get($response, 'body.usage') : [],
            responseId: data_get($response, 'body.id'),
        );

        $this->cache->put($cacheKey, [
            'data' => $result->data,
            'raw_response' => $result->rawResponse,
            'model' => $result->model,
            'usage' => $result->usage,
            'response_id' => $result->responseId,
        ]);

        $this->logger->log([
            'tenant_id' => $context->tenantId,
            'user_id' => $context->userId,
            'type' => 'workout',
            'operation' => 'generation',
            'model' => $result->model,
            'cache_key' => $cacheKey,
            'cache_hit' => false,
            'retrieval_mode' => $retrieval->mode,
            'vector_store_id' => $retrieval->vectorStoreId,
            'file_id' => $retrieval->fileId,
            'http_status' => $response['status'] ?? 200,
            'latency_ms' => $result->latencyMs,
            'usage' => $result->usage,
            'request_payload' => $payload,
            'response_payload' => $result->rawResponse,
            'metadata' => [
                'candidate_count' => count($retrieval->candidates),
                'response_id' => $result->responseId,
            ],
        ]);

        return $result;
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['weekly_plan'],
            'properties' => [
                'weekly_plan' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['day', 'focus', 'exercises'],
                        'properties' => [
                            'day' => ['type' => 'string'],
                            'focus' => ['type' => 'string'],
                            'exercises' => [
                                'type' => 'array',
                                'minItems' => 5,
                                'maxItems' => 5,
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'required' => ['name', 'category', 'sets', 'reps', 'rest', 'notes', 'steps', 'remote_exercise_id', 'workoutx_name'],
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'category' => ['type' => 'string', 'enum' => ['specific', 'cardio']],
                                        'sets' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 6],
                                        'reps' => ['type' => 'string'],
                                        'rest' => ['type' => 'string'],
                                        'notes' => ['type' => 'string'],
                                        'steps' => [
                                            'type' => 'array',
                                            'minItems' => 2,
                                            'maxItems' => 5,
                                            'items' => ['type' => 'string'],
                                        ],
                                        'remote_exercise_id' => ['type' => 'string'],
                                        'workoutx_name' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildPayload(WorkoutGenerationContext $context, WorkoutRetrievalResult $retrieval): array
    {
        return [
            'model' => (string) config('services.openai.responses_model', 'gpt-4o-mini'),
            'temperature' => 0.15,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => 'Voce e um especialista em treino, biomecanica e seguranca. Responda apenas com JSON valido que obedece exatamente ao schema. Nunca invente exercicios, ids, workoutx_name ou cardio fora do catalogo recuperado. Use apenas exercicios semanticamente relevantes ao perfil do usuario.',
                    ]],
                ],
                [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => $this->userInstruction($context, $retrieval),
                    ]],
                ],
            ],
            'tools' => $retrieval->vectorStoreId !== null
                ? [[
                    'type' => 'file_search',
                    'vector_store_ids' => [$retrieval->vectorStoreId],
                    'max_num_results' => min(count($retrieval->candidates) + 8, 24),
                ]]
                : [],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'weekly_workout_plan',
                    'schema' => $this->schema(),
                    'strict' => true,
                ],
            ],
        ];
    }

    private function userInstruction(WorkoutGenerationContext $context, WorkoutRetrievalResult $retrieval): string
    {
        return implode("\n\n", array_filter([
            'Perfil do usuario: ' . json_encode($context->profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Dias esperados no weekly_plan: ' . ($context->expectedTrainingDays ?? 'indefinido'),
            'Treino anterior resumido: ' . json_encode($this->compactPreviousWorkout($context->previousWorkoutPlan), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Modo de retrieval: ' . $retrieval->mode,
            'Exercicios prioritarios recuperados: ' . json_encode($retrieval->compactCandidates(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $context->adjustmentRequest !== null ? 'Ajustes obrigatorios do treinador: ' . $context->adjustmentRequest : null,
            $context->conservativeMode ? 'Refine com criterio conservador mantendo intensidade suficiente, eliminando redundancias e movimentos de risco.' : null,
            'Regras obrigatorias: exatamente 5 exercicios por dia; exatamente 4 specific e 1 cardio; focus unico por dia; nao repetir exercicios no mesmo dia; nao repetir exercicios em dias consecutivos; cardio compativel com restricoes clinicas; steps de 2 a 5 itens; name em pt-BR; workoutx_name em ingles exatamente como no catalogo; remote_exercise_id valido.',
        ]));
    }

    private function compactPreviousWorkout(array $previousWorkoutPlan): array
    {
        $weeklyPlan = is_array($previousWorkoutPlan['weekly_plan'] ?? null)
            ? $previousWorkoutPlan['weekly_plan']
            : [];

        return collect($weeklyPlan)
            ->map(function (mixed $day): array {
                return [
                    'day' => data_get($day, 'day'),
                    'focus' => data_get($day, 'focus'),
                    'exercises' => collect(data_get($day, 'exercises', []))
                        ->map(fn(mixed $exercise): array => [
                            'name' => data_get($exercise, 'name'),
                            'remote_exercise_id' => data_get($exercise, 'remote_exercise_id'),
                            'workoutx_name' => data_get($exercise, 'workoutx_name'),
                            'category' => data_get($exercise, 'category'),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }
}