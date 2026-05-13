<?php

namespace App\Services\AI;

use App\DTOs\AI\StructuredResponseResult;
use App\Exceptions\AI\WorkoutValidationException;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Services\Workout\Planning\WorkoutPlanningEngine;
use App\Services\Workout\Planning\WorkoutRepairEngine;
use Illuminate\Validation\ValidationException;

class AiService
{
    public const WORKOUT_PROMPT_VERSION = '2026-05-13-deterministic-planning-v1';
    private const MAX_WORKOUT_REPAIR_ATTEMPTS = 2;

    public function __construct(
        private readonly WorkoutGenerationContextFactory $contextFactory,
        private readonly WorkoutExerciseRetrievalService $retrievalService,
        private readonly WorkoutPlanGeneratorService $generatorService,
        private readonly WorkoutPlanCriticService $criticService,
        private readonly ValidationService $validationService,
        private readonly WorkoutPlanningEngine $planningEngine,
        private readonly WorkoutRepairEngine $repairEngine,
        private readonly OpenAIResponsesClient $client,
        private readonly AiResponseCacheService $cache,
        private readonly AiRequestLogger $logger,
    ) {}

    public function generateWorkout(
        User $user,
        ?Tenant $tenant,
        bool $conservativeMode = false,
        ?string $adjustmentRequest = null,
    ): array {
        $context = $this->contextFactory->make($user, $tenant, $conservativeMode, $adjustmentRequest);
        $retrieval = $this->retrievalService->retrieve($context);
        $planningPayload = $this->planningEngine->plan($context, $retrieval);
        $candidatePlan = $this->generatorService->generate($context, $retrieval, $planningPayload)->data;

        for ($attempt = 0; $attempt <= self::MAX_WORKOUT_REPAIR_ATTEMPTS; $attempt++) {
            try {
                $validated = $this->validationService->validateWorkoutResponse($candidatePlan, $planningPayload);

                return array_merge($validated, [
                    'quality_scores' => $planningPayload['quality_scores'] ?? [],
                ]);
            } catch (ValidationException $exception) {
                if ($attempt === self::MAX_WORKOUT_REPAIR_ATTEMPTS) {
                    throw $exception;
                }

                $candidatePlan = $this->repairEngine->repair(
                    $context,
                    $retrieval,
                    $planningPayload,
                    $candidatePlan,
                    WorkoutValidationException::fromValidationException($exception),
                );
            }
        }

        throw ValidationException::withMessages([
            'workout' => 'Unable to generate a valid workout plan.',
        ]);
    }

    public function generateRecommendations(User $user, ?Tenant $tenant): array
    {
        $physicalData = $user->physicalData()->first();
        $preference = $user->preference()->first();
        $payload = $this->buildRecommendationsPayload([
            'goal' => $user->goal,
            'activity_level' => $physicalData?->activity_level,
            'available_hours' => $preference?->available_hours ?? [],
            'training_frequency' => $preference?->training_frequency,
        ]);
        $cacheKey = $this->cache->buildKey('recommendations', [
            'tenant_id' => $tenant?->id,
            'user_id' => $user->id,
            'payload' => $payload,
        ]);
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached['data'] ?? [];
        }

        $response = $this->client->createResponse($payload);
        $decoded = $this->client->decodeStructuredOutput($response['body'] ?? []);

        $result = new StructuredResponseResult(
            data: $decoded,
            rawResponse: $response['body'] ?? [],
            cacheHit: false,
            cacheKey: $cacheKey,
            model: (string) data_get($response, 'body.model', config('services.openai.recommendations_model', 'gpt-4o-mini')),
            latencyMs: $response['latency_ms'] ?? null,
            usage: is_array(data_get($response, 'body.usage')) ? data_get($response, 'body.usage') : [],
            responseId: data_get($response, 'body.id'),
        );

        $this->cache->put($cacheKey, [
            'data' => $result->data,
            'raw_response' => $result->rawResponse,
        ]);

        $this->logger->log([
            'tenant_id' => $tenant?->id,
            'user_id' => $user->id,
            'type' => 'recommendations',
            'operation' => 'generation',
            'model' => $result->model,
            'cache_key' => $cacheKey,
            'cache_hit' => false,
            'http_status' => $response['status'] ?? 200,
            'latency_ms' => $result->latencyMs,
            'usage' => $result->usage,
            'request_payload' => $payload,
            'response_payload' => $result->rawResponse,
        ]);

        return $result->data;
    }

    public function workoutPromptVersion(): string
    {
        return self::WORKOUT_PROMPT_VERSION;
    }

    private function buildRecommendationsPayload(array $input): array
    {
        return [
            'model' => (string) config('services.openai.recommendations_model', config('services.openai.responses_model', 'gpt-4o-mini')),
            'temperature' => 0.2,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => 'Voce gera recomendacoes objetivas de saude e cardio. Responda apenas com JSON valido no schema pedido.',
                    ]],
                ],
                [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => 'Dados: ' . json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'wellbeing_recommendations',
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['recommendations', 'cardio_plan'],
                        'properties' => [
                            'recommendations' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'cardio_plan' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'required' => ['type', 'duration', 'frequency'],
                                    'properties' => [
                                        'type' => ['type' => 'string'],
                                        'duration' => ['type' => 'string'],
                                        'frequency' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'strict' => true,
                ],
            ],
        ];
    }
}
