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
    public const WORKOUT_PROMPT_VERSION = '2026-05-13-deterministic-planning-v2';
    private const MAX_WORKOUT_REPAIR_ATTEMPTS = 2;

    public function __construct(
        private readonly WorkoutGenerationContextFactory $contextFactory,
        private readonly WorkoutExerciseRetrievalService $retrievalService,
        private readonly WorkoutPlanGeneratorService $generatorService,
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
                $candidatePlan = $this->fallbackToPlanningBaseIfStructureWasCompromised($candidatePlan, $planningPayload);
                $validated = $this->validationService->validateWorkoutResponse($candidatePlan, $planningPayload);

                return array_merge($validated, [
                    'quality_scores' => $planningPayload['quality_scores'] ?? [],
                    'generation_insights' => $this->buildGenerationInsights($planningPayload),
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

    private function fallbackToPlanningBaseIfStructureWasCompromised(array $candidatePlan, array $planningPayload): array
    {
        $plannedDays = is_array($planningPayload['selected_days'] ?? null)
            ? $planningPayload['selected_days']
            : [];
        $currentDays = is_array($candidatePlan['weekly_plan'] ?? null)
            ? $candidatePlan['weekly_plan']
            : [];

        if ($plannedDays === []) {
            return $candidatePlan;
        }

        if (count($currentDays) < count($plannedDays)) {
            return $this->repairEngine->rebuildFromPlanning($planningPayload);
        }

        $plannedLabels = collect($plannedDays)
            ->map(static fn(array $day): string => trim((string) ($day['label'] ?? $day['day'] ?? '')))
            ->filter()
            ->values();
        $currentLabels = collect($currentDays)
            ->map(static fn(array $day): string => trim((string) ($day['day'] ?? '')))
            ->filter()
            ->values();

        if ($plannedLabels->diff($currentLabels)->isNotEmpty()) {
            return $this->repairEngine->rebuildFromPlanning($planningPayload);
        }

        foreach ($plannedDays as $index => $plannedDay) {
            $candidateDay = $currentDays[$index] ?? null;

            if (! is_array($candidateDay)) {
                return $this->repairEngine->rebuildFromPlanning($planningPayload);
            }

            $plannedExercises = is_array($plannedDay['selected_exercises'] ?? null)
                ? $plannedDay['selected_exercises']
                : [];
            $candidateExercises = is_array($candidateDay['exercises'] ?? null)
                ? $candidateDay['exercises']
                : [];

            if (count($candidateExercises) !== count($plannedExercises)) {
                return $this->repairEngine->rebuildFromPlanning($planningPayload);
            }
        }

        return $candidatePlan;
    }

    private function buildGenerationInsights(array $planningPayload): array
    {
        $trainingMemory = is_array($planningPayload['training_memory'] ?? null)
            ? $planningPayload['training_memory']
            : [];
        $imbalanceFlags = is_array($trainingMemory['imbalance_flags'] ?? null)
            ? $trainingMemory['imbalance_flags']
            : [];
        $volumeDistribution = is_array($planningPayload['volume_distribution'] ?? null)
            ? $planningPayload['volume_distribution']
            : [];
        $selectedDays = is_array($planningPayload['selected_days'] ?? null)
            ? $planningPayload['selected_days']
            : [];
        $references = [];
        $improvements = [];

        if (($imbalanceFlags['horizontal_push_excess'] ?? false) === true) {
            $references[] = 'Historico recente com excesso de empurradas horizontais.';
        }

        if (($imbalanceFlags['vertical_pull_deficit'] ?? false) === true) {
            $references[] = 'Baixa frequencia de puxadas verticais nas semanas anteriores.';
            $improvements[] = 'A semana foi reequilibrada com maior presenca de puxadas verticais e remadas de suporte.';
        }

        if (($imbalanceFlags['shoulder_sensitive'] ?? false) === true) {
            $references[] = 'Restricoes e lesoes indicam sensibilidade anterior no ombro.';
            $improvements[] = 'A selecao priorizou alternativas com menor estresse articular para o ombro.';
        }

        if (($imbalanceFlags['chest_overloaded'] ?? false) === true) {
            $chestSets = (int) data_get($volumeDistribution, 'peito.weekly_sets', 0);
            $backSets = (int) data_get($volumeDistribution, 'costas.weekly_sets', 0);
            $references[] = 'Volume recente de peito acima do ideal para o momento.';
            $improvements[] = 'O volume semanal de peito foi reduzido para ' . $chestSets . ' series, enquanto costas recebeu ' . $backSets . ' series planejadas.';
        }

        if (($trainingMemory['overused_movements'] ?? []) !== []) {
            $improvements[] = 'Exercicios muito repetidos passaram a ser substituidos por variacoes estruturais mais seguras quando havia alternativa equivalente.';
        }

        return [
            'summary' => [
                'weekly_frequency' => (int) ($planningPayload['weekly_frequency'] ?? count($selectedDays)),
                'split_labels' => array_values(array_filter(array_map(static fn(array $day): string => trim((string) ($day['focus'] ?? $day['focus_label'] ?? '')), $selectedDays))),
            ],
            'statistics' => [
                'training_days' => count($selectedDays),
                'specific_exercises' => count(array_filter(
                    collect($selectedDays)->flatMap(static fn(array $day): array => is_array($day['selected_exercises'] ?? null) ? $day['selected_exercises'] : [])->all(),
                    static fn(array $exercise): bool => ($exercise['category'] ?? 'specific') === 'specific'
                )),
                'cardio_blocks' => count(array_filter(
                    collect($selectedDays)->flatMap(static fn(array $day): array => is_array($day['selected_exercises'] ?? null) ? $day['selected_exercises'] : [])->all(),
                    static fn(array $exercise): bool => ($exercise['category'] ?? '') === 'cardio'
                )),
            ],
            'references' => array_values(array_unique($references)),
            'improvements' => array_values(array_unique($improvements)),
        ];
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
