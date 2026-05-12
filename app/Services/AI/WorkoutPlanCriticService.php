<?php

namespace App\Services\AI;

use App\DTOs\AI\StructuredResponseResult;
use App\DTOs\AI\WorkoutGenerationContext;
use App\DTOs\AI\WorkoutRetrievalResult;
use App\Exceptions\AI\WorkoutValidationException;

class WorkoutPlanCriticService
{
    public function __construct(
        private readonly OpenAIResponsesClient $client,
        private readonly WorkoutPlanGeneratorService $generatorService,
        private readonly AiRequestLogger $logger,
    ) {}

    public function repair(
        WorkoutGenerationContext $context,
        WorkoutRetrievalResult $retrieval,
        array $invalidPlan,
        WorkoutValidationException $validationException,
    ): StructuredResponseResult {
        $payload = [
            'model' => (string) config('services.openai.responses_model', 'gpt-4o-mini'),
            'temperature' => 0.1,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => 'Voce corrige planos de treino invalidos. Reescreva o JSON inteiro obedecendo todas as regras e sem inventar ids.',
                    ]],
                ],
                [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => implode("\n\n", [
                            'Problemas identificados: ' . json_encode($validationException->issues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'Plano invalido: ' . json_encode($invalidPlan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'Perfil do usuario: ' . json_encode($context->profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'Exercicios recuperados permitidos: ' . json_encode($retrieval->compactCandidates(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'Regras obrigatorias: exatamente 5 exercicios por dia, 4 specific e 1 cardio, sem repeticoes desnecessarias, focus coerente, ids validos e steps de 2 a 5 itens.',
                        ]),
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
                    'schema' => $this->generatorService->schema(),
                    'strict' => true,
                ],
            ],
        ];

        $response = $this->client->createResponse($payload);
        $decoded = $this->client->decodeStructuredOutput($response['body'] ?? []);

        $result = new StructuredResponseResult(
            data: $decoded,
            rawResponse: $response['body'] ?? [],
            cacheHit: false,
            cacheKey: '',
            model: (string) data_get($response, 'body.model', config('services.openai.responses_model', 'gpt-4o-mini')),
            latencyMs: $response['latency_ms'] ?? null,
            usage: is_array(data_get($response, 'body.usage')) ? data_get($response, 'body.usage') : [],
            responseId: data_get($response, 'body.id'),
        );

        $this->logger->log([
            'tenant_id' => $context->tenantId,
            'user_id' => $context->userId,
            'type' => 'workout',
            'operation' => 'critic',
            'model' => $result->model,
            'retrieval_mode' => $retrieval->mode,
            'vector_store_id' => $retrieval->vectorStoreId,
            'file_id' => $retrieval->fileId,
            'http_status' => $response['status'] ?? 200,
            'latency_ms' => $result->latencyMs,
            'usage' => $result->usage,
            'request_payload' => $payload,
            'response_payload' => $result->rawResponse,
            'metadata' => ['issues' => $validationException->issues],
        ]);

        return $result;
    }
}
