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

    public function generate(WorkoutGenerationContext $context, WorkoutRetrievalResult $retrieval, array $planningPayload = []): StructuredResponseResult
    {
        $payload = $this->buildPayload($context, $retrieval, $planningPayload);
        $cacheKey = $this->cache->buildKey('workout_generation', [
            'prompt_version' => AiService::WORKOUT_PROMPT_VERSION,
            'context' => $context->promptFingerprint(),
            'retrieval_mode' => $retrieval->mode,
            'vector_store_id' => $retrieval->vectorStoreId,
            'candidate_ids' => array_map(static fn(array $candidate): string => (string) ($candidate['remote_exercise_id'] ?? ''), $retrieval->compactCandidates()),
            'planning_hash' => hash('sha256', json_encode($planningPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ]);

        $shouldUseCache = (bool) config('services.openai.workout_generation_cache_enabled', false);

        $cached = $shouldUseCache ? $this->cache->get($cacheKey) : null;

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

        if ($shouldUseCache) {
            $this->cache->put($cacheKey, [
                'data' => $result->data,
                'raw_response' => $result->rawResponse,
                'model' => $result->model,
                'usage' => $result->usage,
                'response_id' => $result->responseId,
            ]);
        }

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

    public function schema(?int $expectedTrainingDays = null): array
    {
        $expectedDays = $expectedTrainingDays !== null && $expectedTrainingDays > 0
            ? $expectedTrainingDays
            : 1;

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['weekly_plan'],
            'properties' => [
                'weekly_plan' => [
                    'type' => 'array',
                    'minItems' => $expectedDays,
                    'maxItems' => $expectedDays,
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
                                        'name' => [
                                            'type' => 'string',
                                            'pattern' => '.*\\S.*',
                                        ],
                                        'category' => ['type' => 'string', 'enum' => ['specific', 'cardio']],
                                        'sets' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 6],
                                        'reps' => [
                                            'type' => 'string',
                                            'pattern' => '.*\\S.*',
                                        ],
                                        'rest' => [
                                            'type' => 'string',
                                            'pattern' => '.*\\S.*',
                                        ],
                                        'notes' => [
                                            'type' => 'string',
                                            'pattern' => '.*\\S.*',
                                        ],
                                        'steps' => [
                                            'type' => 'array',
                                            'minItems' => 2,
                                            'maxItems' => 5,
                                            'items' => [
                                                'type' => 'string',
                                                'pattern' => '.*\\S.*',
                                            ],
                                        ],
                                        'remote_exercise_id' => [
                                            'type' => 'string',
                                            'pattern' => '.*\\S.*',
                                        ],
                                        'workoutx_name' => [
                                            'type' => 'string',
                                            'pattern' => '.*\\S.*',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildPayload(WorkoutGenerationContext $context, WorkoutRetrievalResult $retrieval, array $planningPayload = []): array
    {
        return [
            'model' => (string) config('services.openai.responses_model', 'gpt-4o-mini'),
            'temperature' => 0.15,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => 'Voce e uma camada assistiva de treino. O backend ja definiu split, distribuicao biomecanica, selecao principal e limites de fadiga. Sua funcao e apenas organizar, contextualizar, suavizar linguagem e completar campos textuais sem alterar a estrutura deterministica. Responda apenas com JSON valido que obedece exatamente ao schema. Nunca invente exercicios, ids, workoutx_name ou cardio fora do catalogo recuperado. Nunca omita dias do weekly_plan. Se houver qualquer duvida estrutural, preserve exatamente os dias, a ordem e os exercicios do weekly_plan_base.',
                    ]],
                ],
                [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => $this->userInstruction($context, $retrieval, $planningPayload),
                    ]],
                ],
            ],
            'tools' => $retrieval->vectorStoreId !== null
                ? [[
                    'type' => 'file_search',
                    'vector_store_ids' => [$retrieval->vectorStoreId],
                    'max_num_results' => min(max(count($retrieval->candidates), 48), 240),
                ]]
                : [],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'weekly_workout_plan',
                    'schema' => $this->schema($context->expectedTrainingDays),
                    'strict' => true,
                ],
            ],
        ];
    }

    private function userInstruction(WorkoutGenerationContext $context, WorkoutRetrievalResult $retrieval, array $planningPayload = []): string
    {
        return implode("\n\n", array_filter([
            'Perfil do usuario: ' . json_encode($context->profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Dias esperados no weekly_plan: ' . ($context->expectedTrainingDays ?? 'indefinido'),
            'Treino anterior resumido: ' . json_encode($this->compactPreviousWorkout($context->previousWorkoutPlan), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Exercicios do treino anterior a evitar quando houver alternativa no mesmo foco: ' . json_encode($this->previousWorkoutExerciseIds($context->previousWorkoutPlan), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Modo de retrieval: ' . $retrieval->mode,
            'Exercicios prioritarios recuperados: ' . json_encode($retrieval->compactCandidates(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Exercicios permitidos por foco: ' . json_encode($retrieval->compactCandidatesByFocus(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $planningPayload !== [] ? 'Plano deterministico obrigatório: ' . json_encode($this->deterministicPlanForPrompt($planningPayload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $context->adjustmentRequest !== null ? 'Ajustes obrigatorios do treinador: ' . $context->adjustmentRequest : null,
            $context->conservativeMode ? 'Refine com criterio conservador mantendo intensidade suficiente, eliminando redundancias e movimentos de risco.' : null,
            'Regra de selecao por foco: siga exatamente o plano deterministico do backend para split, foco, ordem e identidades dos exercicios. Use cardio apenas do grupo cardio. Nao introduza exercicios fora dos ids planejados.',
            'Regra de variacao entre geracoes: trate os remote_exercise_id do treino anterior como bloqueados. So reutilize um exercicio anterior se realmente nao houver alternativa suficiente no mesmo foco do dia.',
            'Regras obrigatorias: weekly_plan deve conter exatamente ' . ($context->expectedTrainingDays ?? 'o numero planejado de') . ' dias e nunca pode omitir o ultimo dia; preserve os mesmos dias e a mesma ordem do weekly_plan_base; exatamente 5 exercicios por dia; exatamente 4 specific e 1 cardio; use o focus definido no plano deterministico; nao repetir exercicios no mesmo dia; nao repetir exercicios em dias consecutivos; cardio compativel com restricoes clinicas; steps de 2 a 5 itens; name em pt-BR; workoutx_name em ingles exatamente como no catalogo; remote_exercise_id obrigatorio; reps, rest e notes nunca vazios.',
            'Se a sua resposta correr risco de ficar com menos dias do que o planejado, copie o weekly_plan_base inteiro e apenas ajuste textos legiveis, sem remover nenhum dia.',
            'Voce nao decide volume, progressao, distribuicao biomecanica ou selecao principal. Apenas converta o plano base em um weekly_plan final legivel e seguro.',
        ]));
    }

    private function deterministicPlanForPrompt(array $planningPayload): array
    {
        return [
            'weekly_frequency' => $planningPayload['weekly_frequency'] ?? null,
            'quality_scores' => $planningPayload['quality_scores'] ?? [],
            'weekly_plan_base' => array_map(function (array $day): array {
                return [
                    'day' => $day['label'] ?? $day['day'] ?? 'Dia',
                    'focus' => $day['focus'] ?? $day['focus_label'] ?? 'Treino',
                    'focuses' => $day['focuses'] ?? [],
                    'patterns' => $day['patterns'] ?? [],
                    'exercises' => array_map(static fn(array $exercise): array => [
                        'name' => $exercise['name'] ?? null,
                        'category' => $exercise['category'] ?? null,
                        'sets' => $exercise['sets'] ?? null,
                        'reps' => $exercise['reps'] ?? null,
                        'rest' => $exercise['rest'] ?? null,
                        'notes' => $exercise['notes'] ?? null,
                        'steps' => $exercise['steps'] ?? null,
                        'remote_exercise_id' => $exercise['remote_exercise_id'] ?? null,
                        'workoutx_name' => $exercise['workoutx_name'] ?? null,
                        'reason' => $exercise['reason'] ?? null,
                    ], $day['selected_exercises'] ?? []),
                ];
            }, $planningPayload['selected_days'] ?? []),
        ];
    }

    private function previousWorkoutExerciseIds(array $previousWorkoutPlan): array
    {
        $weeklyPlan = is_array($previousWorkoutPlan['weekly_plan'] ?? null)
            ? $previousWorkoutPlan['weekly_plan']
            : [];

        return collect($weeklyPlan)
            ->flatMap(fn(mixed $day): array => is_array(data_get($day, 'exercises')) ? data_get($day, 'exercises') : [])
            ->map(fn(mixed $exercise): string => trim((string) data_get($exercise, 'remote_exercise_id', '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
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
