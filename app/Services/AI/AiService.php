<?php

namespace App\Services\AI;

use App\Models\AI\AiLog;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use App\Services\Workouts\ExerciseCatalogService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiService
{
    public const WORKOUT_PROMPT_VERSION = '2026-05-12-workoutx-local-catalog-harder-previous-workout-rules';

    public function __construct(
        private readonly ?ExerciseCatalogService $exerciseCatalogService = null,
    ) {}

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
            . "Ao montar o novo plano, trate o treino anterior como referencia obrigatoria de variacao. Nao reaproveite mecanicamente os mesmos exercicios, o mesmo cardio principal ou combinacoes quase identicas de padrao de movimento quando houver alternativa segura no catalogo local. Substitua por variacoes equivalentes, mantenha o mesmo foco muscular do dia quando fizer sentido, preserve a estrutura 4+1 por dia e so repita um exercicio anterior se isso for tecnicamente indispensavel para seguranca, contexto clinico ou falta real de opcao melhor no catalogo.";
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
            . "Voce e um especialista de elite em educacao fisica, hipertrofia, biomecanica, periodizacao e prescricao de treino baseada em evidencias.\n\n"
            . "Sua funcao e montar um plano de treino tecnicamente consistente, seguro, intenso na medida certa e altamente eficaz para o objetivo do usuario, com selecao inteligente de exercicios, distribuicao muscular coerente e execucao realista.\n\n"
            . "# =========================\n"
            . "# REGRAS CRITICAS\n"
            . "# =========================\n\n"
            . "- Trate seguranca, aderencia ao objetivo e coerencia biomecanica como prioridade absoluta.\n"
            . "- NUNCA prescreva exercicios que contrariem restricoes medicas, lesoes, limitacoes articulares ou sinais claros de risco mecanico.\n"
            . "- NUNCA monte treino generico; adapte selecao, volume, intensidade e dificuldade ao nivel fisico, objetivo e contexto do usuario.\n"
            . "- NUNCA misture grandes grupos musculares no mesmo dia quando isso comprometer a qualidade do foco (ex.: pernas com ombro, pernas com peito, pernas com costas).\n"
            . "- Cada dia deve ter foco muscular unico, claro e coerente do inicio ao fim.\n"
            . "- Cada dia deve conter EXATAMENTE 5 exercicios.\n"
            . "- Dos 5 exercicios do dia: EXATAMENTE 4 devem ser do foco muscular do dia e EXATAMENTE 1 deve ser cardio.\n"
            . "- O cardio deve ser compativel com o perfil do usuario e com as restricoes clinicas, priorizando baixo a moderado impacto quando necessario.\n"
            . "- Cada exercicio deve conter um array steps com 2 a 5 passos curtos, claros e acionaveis.\n"
            . "- Cada exercicio deve conter workoutx_name e remote_exercise_id validos, extraidos do catalogo local fornecido no prompt.\n"
            . "- workoutx_name deve permanecer em INGLES exatamente como esta no catalogo local.\n"
            . "- O campo name deve SEMPRE ser salvo em pt-BR para exibicao ao usuario final.\n"
            . "- Evite redundancia: nao repetir exercicios, padroes de movimento ou variacoes quase identicas no mesmo dia sem justificativa tecnica.\n"
            . "- Prefira exercicios com melhor relacao entre seguranca, eficiencia e capacidade real de progressao para este usuario.\n\n"
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
            . $this->buildWorkoutCatalogSection()
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
            . "          \"remote_exercise_id\": \"0043\",\n"
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
            . "          \"remote_exercise_id\": \"1160\",\n"
            . "          \"workoutx_name\": \"incline treadmill walk\"\n"
            . "        }\n"
            . "      ]\n"
            . "    }\n"
            . "  ]\n"
            . "}\n\n"
            . "VALIDACAO FINAL ANTES DE RESPONDER:\n"
            . "- O weekly_plan contem exatamente a quantidade de dias pedida pela frequencia de treino?\n"
            . "- Todos os dias do weekly_plan estao completos, distintos e utilizaveis, sem placeholders, exemplos parciais ou campos vazios?\n"
            . "- Cada dia tem exatamente 5 exercicios?\n"
            . "- Cada dia tem exatamente 4 com category=specific e 1 com category=cardio?\n"
            . "- O focus do dia e unico e nao mistura grupos?\n"
            . "- Dia de pernas contem somente exercicios de pernas + 1 cardio?\n"
            . "- Existe coerencia tecnica entre focus, exercicios escolhidos, volume, repeticoes, descanso e nivel do usuario?\n"
            . "- Existe qualquer exercicio proibido, arriscado ou incoerente com lesoes, restricoes e contexto clinico do usuario?\n"
            . "- Cada exercicio possui remote_exercise_id real do catalogo local?\n"
            . "- Cada exercicio usa workoutx_name exatamente igual ao catalogo local, sem inventar nomes, ids ou variacoes fora da base?\n"
            . "- Todos os campos name estao em pt-BR para salvar no banco e exibir ao usuario?\n"
            . "- Todos os exercicios possuem workoutx_name em ingles para busca?\n"
            . "- Todos os exercicios possuem steps validos?\n"
            . "- Existe repeticao desnecessaria de exercicios, padroes de movimento ou variacoes quase identicas no mesmo dia ou em dias consecutivos?\n"
            . "Se qualquer resposta for NAO ou SIM para uma checagem de erro, voce DEVE corrigir o plano inteiro antes de responder. Nao explique, nao resuma e nao entregue texto fora do JSON final valido.";

        if ($this->goalPrioritizesGymMachines($input)) {
            $prompt .= "\n\n# =========================\n"
                . "# REGRA ESPECIFICA PARA MUSCULACAO E HIPERTROFIA\n"
                . "# =========================\n\n"
                . "Se o foco do usuario for musculacao, ganho de massa muscular ou hipertrofia, priorize exercicios feitos em aparelhos e maquinas tipicas de academia de musculacao, alem de equipamentos tradicionais de musculacao quando isso gerar melhor estabilidade, controle de carga e progressao.\n"
                . "Evite montar a base do treino com materiais basicos ou solucoes simplificadas, como vassoura, cadeira, elasticos, cabo improvisado, peso corporal isolado ou acessorios leves, exceto se o catalogo local nao oferecer alternativa melhor e houver justificativa tecnica clara de seguranca ou contexto clinico.\n"
                . "Para esse perfil, prefira selecoes com cara real de academia, com foco em hipertrofia, tensao mecanica, estabilidade e progressao consistente de carga.";
        }

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
                . "Reestruture o treino com criterio tecnico de especialista. Corrija qualquer combinacao ruim, elimine exercicios redundantes e entregue um plano mais assertivo, com estimulo forte, progressao coerente e alta qualidade tecnica, sem violar restricoes clinicas, lesoes, a estrutura 4+1 por dia e todas as regras criticas de seguranca.";
        }

        return $prompt;
    }

    private function buildWorkoutCatalogSection(): string
    {
        $snapshot = $this->exerciseCatalogService()->buildAiCatalogSnapshot();
        $catalog = $snapshot['catalog'] ?? [];
        $bucketLimit = (int) ($snapshot['bucket_limit'] ?? 12);

        if (! is_array($catalog) || $catalog === []) {
            return "# =========================\n"
                . "# CATALOGO LOCAL DE EXERCICIOS\n"
                . "# =========================\n\n"
                . "Catalogo local ainda nao sincronizado. Se remote_exercise_id nao estiver disponivel, monte o treino com workoutx_name coerente para busca posterior.\n\n";
        }

        return "# =========================\n"
            . "# CATALOGO LOCAL ENXUTO POR FOCO MUSCULAR (OBRIGATORIO)\n"
            . "# =========================\n\n"
            . "Abaixo esta um catalogo reduzido por foco muscular, limitado a {$bucketLimit} exercicios por grupo para reduzir erro e tokens.\n"
            . "Use SOMENTE exercicios presentes neste catalogo local. Nao invente remote_exercise_id, workoutx_name, target ou equipment.\n"
            . "Para os 4 exercicios especificos, escolha itens do grupo de foco coerente com o focus do dia. Para o cardio, escolha itens do grupo cardio.\n"
            . "Cada exercicio da resposta final deve trazer remote_exercise_id e workoutx_name exatamente como aparecem abaixo. O campo name deve ficar em pt-BR. Quando localized_name_pt_br estiver preenchido, use exatamente esse valor. Quando estiver vazio, traduza para pt-BR antes de responder.\n\n"
            . json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    }

    private function exerciseCatalogService(): ExerciseCatalogService
    {
        return $this->exerciseCatalogService ?? new ExerciseCatalogService();
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

    private function goalPrioritizesGymMachines(array $input): bool
    {
        $goal = mb_strtolower(trim((string) ($input['goal'] ?? '')));

        if ($goal === '') {
            return false;
        }

        return str_contains($goal, 'hipertrof')
            || str_contains($goal, 'muscula')
            || str_contains($goal, 'massa muscular')
            || str_contains($goal, 'ganho de massa');
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
