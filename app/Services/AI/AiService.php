<?php

namespace App\Services\AI;

use App\Models\AI\AiLog;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiService
{
    public const WORKOUT_PROMPT_VERSION = '2026-05-04-workoutx-name';

    public function generateWorkout(
        User $user,
        ?Tenant $tenant,
        bool $conservativeMode = false,
        ?string $adjustmentRequest = null,
    ): array {
        $input = $this->workoutInputFromUser($user);
        $prompt = $this->buildWorkoutPrompt($input, $conservativeMode, $adjustmentRequest);
        $workoutQuery = Workout::query()
            ->where('user_id', $user->id)
            ->where('status', 'done');
        $workout = $workoutQuery
            ->latest('id')
            ->first();
        $previousWorkoutPlan = $workout?->workout_plan;
        $prompt .= $this->preparePreviousWorkoutAdjustmentPrompt($previousWorkoutPlan ?? []);

        return $this->callOpenAi($prompt, $user, $tenant, 'workout');
    }

    private function preparePreviousWorkoutAdjustmentPrompt(array $previousWorkoutPlan): string
    {
        return "# =========================\n"
            . "# TREINO ANTERIOR\n"
            . "# =========================\n\n"
            . "O plano de treino anterior gerado para o usuario foi:\n\n"
            . json_encode($previousWorkoutPlan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
            . " Evite repetir os mesmos exercicios do plano anterior, buscando variacoes seguras e eficientes, mas mantendo a estrutura 4+1 por dia e obedecendo as regras criticas de seguranca e estrutura do treino.";
    }

    public function generateRecommendations(User $user, ?Tenant $tenant): array
    {
        $input = $this->recommendationInputFromUser($user);
        $prompt = $this->buildRecommendationsPrompt($input);

        return $this->callOpenAi($prompt, $user, $tenant, 'recommendations');
    }

    public function workoutPromptVersion(): string
    {
        return self::WORKOUT_PROMPT_VERSION;
    }

    private function callOpenAi(string $prompt, User $user, ?Tenant $tenant, string $type): array
    {
        $apiKey = (string) config('services.openai.api_key');
        $model = (string) config('services.openai.model', 'gpt-4o-mini');
        $timeoutSeconds = (int) config('services.openai.timeout', 90);
        $connectTimeoutSeconds = (int) config('services.openai.connect_timeout', 20);
        $retryTimes = (int) config('services.openai.retry_times', 3);
        $retrySleepMs = (int) config('services.openai.retry_sleep_ms', 1200);

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is missing.');
        }

        try {
            $response = Http::connectTimeout($connectTimeoutSeconds)
                ->timeout($timeoutSeconds)
                ->retry(
                    $retryTimes,
                    fn(int $attempt): int => $retrySleepMs * $attempt,
                    fn($exception): bool => $exception instanceof ConnectionException,
                )
                ->withToken($apiKey)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => 'You must return only valid JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $message = (string) $exception->getMessage();

            if (str_contains($message, 'cURL error 28')) {
                throw new RuntimeException('Tempo limite excedido ao consultar a IA. Tente novamente em instantes.');
            }

            throw new RuntimeException('Falha de conexao ao consultar a IA. Verifique a conectividade e tente novamente.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI request failed with status ' . $response->status());
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');

        $decoded = json_decode($this->extractJson($content), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid JSON returned by AI.');
        }

        $this->logAiCall($user, $tenant, $type, $prompt, $content);

        return $decoded;
    }

    private function logAiCall(User $user, ?Tenant $tenant, string $type, string $prompt, string $responseContent): void
    {
        AiLog::query()->create([
            'tenant_id' => $tenant?->id,
            'user_id' => $user->id,
            'type' => $type,
            'prompt_hash' => md5($prompt),
            'response_size' => strlen($responseContent),
        ]);
    }

    private function extractJson(string $content): string
    {
        $trimmed = trim($content);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```json\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/^```\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }

    private function buildWorkoutPrompt(
        array $input,
        bool $conservativeMode = false,
        ?string $adjustmentRequest = null,
    ): string {
        $prompt = "# =========================\n"
            . "# CONTEXTO\n"
            . "# =========================\n\n"
            . "Voce e um especialista em educacao fisica.\n\n"
            . "Sua funcao e gerar um plano de treino seguro e eficiente.\n\n"
            . "# =========================\n"
            . "# REGRAS CRITICAS\n"
            . "# =========================\n\n"
            . "- NUNCA sugerir exercicios que agravem lesoes\n"
            . "- Respeitar nivel fisico do usuario\n"
            . "- Adaptar treino ao objetivo\n"
            . "- Evitar sobrecarga\n"
            . "- Priorizar seguranca\n"
            . "- NUNCA misturar grandes grupos musculares no mesmo dia (ex.: pernas com ombro, pernas com peito, pernas com costas)\n"
            . "- Cada dia deve ter foco muscular unico e coerente\n"
            . "- Cada dia deve conter EXATAMENTE 5 exercicios\n"
            . "- Dos 5 exercicios do dia: EXATAMENTE 4 devem ser do foco muscular do dia e EXATAMENTE 1 deve ser cardio\n"
            . "- Cada exercicio deve conter um array steps com 2 a 5 passos curtos e objetivos\n"
            . "- Cada exercicio deve conter workoutx_name para busca exata na WorkoutX API\n"
            . "- workoutx_name deve estar em INGLES e corresponder ao nome do exercicio na base da WorkoutX\n"
            . "- O cardio deve ser de baixo a moderado impacto, compativel com o perfil do usuario\n\n"
            . "# =========================\n"
            . "# REGRAS DE ESTRUTURA DO TREINO (OBRIGATORIAS)\n"
            . "# =========================\n\n"
            . "1) Nao repetir o mesmo grupo muscular principal em dias consecutivos.\n"
            . "2) Dia de pernas deve conter apenas pernas (4 exercicios de pernas + 1 cardio).\n"
            . "3) Em todos os dias, o campo focus deve refletir somente o grupo muscular principal do dia.\n"
            . "4) Se o usuario tiver lesao/restricao, substituir exercicios por variacoes seguras e manter a estrutura 4+1.\n\n"
            . "# =========================\n"
            . "# DADOS DO USUARIO\n"
            . "# =========================\n\n"
            . "Idade: " . ($input['age'] ?? 'N/A') . "\n"
            . "Sexo: " . ($input['gender'] ?? 'N/A') . "\n"
            . "Altura: " . ($input['height'] ?? 'N/A') . "\n"
            . "Peso: " . ($input['weight'] ?? 'N/A') . "\n"
            . "IMC: " . ($input['imc'] ?? 'N/A') . "\n"
            . "Nivel de atividade: " . ($input['activity_level'] ?? 'N/A') . "\n\n"
            . "Frequencia de treino: " . ($input['training_frequency'] ?? 'N/A') . "\n\n"
            . "Objetivo: " . ($input['goal'] ?? 'N/A') . "\n\n"
            . "Restricoes medicas:\n"
            . (string) ($input['restrictions'] ?? 'Nenhuma') . "\n\n"
            . "Lesoes:\n"
            . (string) ($input['injuries'] ?? 'Nenhuma') . "\n\n"
            . "# =========================\n"
            . "# REGRA DE QUANTIDADE DE DIAS (OBRIGATORIA)\n"
            . "# =========================\n\n"
            . $this->buildTrainingFrequencyInstruction($input) . "\n\n"
            . "# =========================\n"
            . "# FORMATO DE RESPOSTA (OBRIGATORIO)\n"
            . "# =========================\n\n"
            . "JSON valido. O weekly_plan deve conter TODOS os dias exigidos pela frequencia, nao apenas um exemplo de dia:\n\n"
            . "{\n"
            . "  \"weekly_plan\": [\n"
            . "    {\n"
            . "      \"day\": \"Segunda\",\n"
            . "      \"focus\": \"Pernas\",\n"
            . "      \"exercises\": [\n"
            . "        {\n"
            . "          \"name\": \"Agachamento livre\",\n"
            . "          \"category\": \"specific\",\n"
            . "          \"sets\": 4,\n"
            . "          \"reps\": \"8-12\",\n"
            . "          \"rest\": \"60s\",\n"
            . "          \"notes\": \"Controle o movimento\",\n"
            . "          \"steps\": [\"Posicione os pes na largura dos ombros\", \"Flexione joelhos e quadril mantendo o tronco firme\", \"Retorne empurrando o chao com os pes\"],\n"
            . "          \"workoutx_name\": \"barbell squat\"\n"
            . "        },\n"
            . "        {\n"
            . "          \"name\": \"Caminhada inclinada\",\n"
            . "          \"category\": \"cardio\",\n"
            . "          \"sets\": 1,\n"
            . "          \"reps\": \"15-20 min\",\n"
            . "          \"rest\": \"0s\",\n"
            . "          \"notes\": \"Cardio leve a moderado\",\n"
            . "          \"steps\": [\"Inicie com passada confortavel\", \"Mantenha postura ereta e respiracao ritmada\", \"Ajuste a inclinacao sem perder estabilidade\"],\n"
            . "          \"workoutx_name\": \"incline treadmill walk\"\n"
            . "        }\n"
            . "      ]\n"
            . "    }\n"
            . "  ]\n"
            . "}\n\n"
            . "VALIDACAO FINAL ANTES DE RESPONDER:\n"
            . "- O weekly_plan contem exatamente a quantidade de dias pedida pela frequencia de treino?\n"
            . "- Cada dia tem exatamente 5 exercicios?\n"
            . "- Cada dia tem exatamente 4 com category=specific e 1 com category=cardio?\n"
            . "- O focus do dia e unico e nao mistura grupos?\n"
            . "- Dia de pernas contem somente exercicios de pernas + 1 cardio?\n"
            . "- Todos os exercicios possuem workoutx_name em ingles para busca?\n"
            . "- Todos os exercicios possuem steps validos?\n"
            . "Se alguma resposta for NAO, corrija antes de enviar o JSON final.";

        $normalizedAdjustmentRequest = trim((string) $adjustmentRequest);

        if ($normalizedAdjustmentRequest !== '') {
            $prompt .= "\n\n# =========================\n"
                . "# AJUSTES SOLICITADOS PELO TREINADOR (OBRIGATORIO)\n"
                . "# =========================\n\n"
                . $normalizedAdjustmentRequest . "\n\n"
                . "Ajuste o plano para obedecer exatamente a solicitacao acima, mantendo todas as regras criticas e a estrutura 4+1 por dia.";
        }

        if ($conservativeMode) {
            $prompt .= "\n\n# =========================\n"
                . "# AJUSTE DE SEGURANCA\n"
                . "# =========================\n\n"
                . "Seja mais conservador. Priorize exercicios de baixo risco, menor impacto e menor intensidade.";
        }

        return $prompt;
    }

    private function buildTrainingFrequencyInstruction(array $input): string
    {
        $trainingFrequency = trim((string) ($input['training_frequency'] ?? ''));
        $daysPerWeek = $this->resolveTrainingDays($trainingFrequency);

        if ($daysPerWeek === null) {
            return 'Se a frequencia de treino estiver indefinida, monte um plano semanal coerente com multiplos dias, nunca retornando apenas um unico dia isolado.';
        }

        return 'A frequencia informada exige EXATAMENTE ' . $daysPerWeek . ' dia(s) de treino no weekly_plan. Se a frequencia for 5x por semana, retorne exatamente 5 dias distintos e completos.';
    }

    private function buildRecommendationsPrompt(array $input): string
    {
        return "# =========================\n"
            . "# CONTEXTO\n"
            . "# =========================\n\n"
            . "Voce e um especialista em saude e bem-estar.\n\n"
            . "# =========================\n"
            . "# OBJETIVO\n"
            . "# =========================\n\n"
            . "Gerar recomendacoes de:\n\n"
            . "- Sono\n"
            . "- Hidratacao\n"
            . "- Cardio\n"
            . "- Rotina saudavel\n\n"
            . "# =========================\n"
            . "# DADOS\n"
            . "# =========================\n\n"
            . "Objetivo: " . ($input['goal'] ?? 'N/A') . "\n"
            . "Nivel de atividade: " . ($input['activity_level'] ?? 'N/A') . "\n"
            . "Horario disponivel: " . json_encode($input['available_hours'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            . "Frequencia de treino: " . ($input['training_frequency'] ?? 'N/A') . "\n\n"
            . "# =========================\n"
            . "# FORMATO\n"
            . "# =========================\n\n"
            . "{\n"
            . "  \"recommendations\": [\n"
            . "    \"Dormir pelo menos 7 horas por noite\",\n"
            . "    \"Beber 2 litros de agua por dia\"\n"
            . "  ],\n"
            . "  \"cardio_plan\": [\n"
            . "    {\n"
            . "      \"type\": \"Caminhada\",\n"
            . "      \"duration\": \"30 minutos\",\n"
            . "      \"frequency\": \"3x por semana\"\n"
            . "    }\n"
            . "  ]\n"
            . "}";
    }

    private function workoutInputFromUser(User $user): array
    {
        $physicalData = $user->physicalData()->first();
        $medicalData = $user->medicalData()->first();
        $preference = $user->preference()->first();

        if ($physicalData === null || $medicalData === null) {
            throw new RuntimeException('Missing physical or medical data for workout generation.');
        }

        return [
            'age' => $user->birth_date?->age,
            'gender' => $user->gender,
            'height' => $user->height,
            'weight' => $user->weight,
            'imc' => $physicalData->imc,
            'activity_level' => $physicalData->activity_level,
            'training_frequency' => $preference?->training_frequency,
            'goal' => $user->goal,
            'restrictions' => $medicalData->restrictions,
            'injuries' => $medicalData->injuries,
        ];
    }

    private function resolveTrainingDays(string $trainingFrequency): ?int
    {
        if ($trainingFrequency === '') {
            return null;
        }

        if (preg_match('/(\d{1,2})/', $trainingFrequency, $matches) !== 1) {
            return null;
        }

        $days = (int) $matches[1];

        return $days > 0 ? $days : null;
    }

    private function recommendationInputFromUser(User $user): array
    {
        $physicalData = $user->physicalData()->first();
        $preference = $user->preference()->first();

        return [
            'goal' => $user->goal,
            'activity_level' => $physicalData?->activity_level,
            'available_hours' => $preference?->available_hours ?? [],
            'training_frequency' => $preference?->training_frequency,
        ];
    }
}
