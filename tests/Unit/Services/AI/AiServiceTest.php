<?php

namespace Tests\Unit\Services\AI;

use App\Models\AI\AiVectorStore;
use App\Models\MedicalData\MedicalData;
use App\Models\PhysicalData\PhysicalData;
use App\Models\Preferences\Preference;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\ExerciseMediaCache;
use App\Models\Workout\Workout;
use App\Services\AI\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_workout_uses_responses_api_with_vector_store_and_json_schema(): void
    {
        config()->set('services.openai.api_key', 'openai-test-key');
        config()->set('services.openai.responses_model', 'gpt-4o-mini');
        config()->set('services.openai.vector_store.scope', 'global');
        config()->set('services.internal_catalog.storage_path', 'ai/test-openai-workout-catalog.json');
        config()->set('services.internal_catalog.vector_store_storage_path', 'ai/test-openai-workout-catalog.jsonl');
        config()->set('services.openai.vector_store.minimum_candidates', 5);
        config()->set('services.openai.prompt_log_path', 'framework/testing/ai-prompts.log');
        Storage::fake('local');
        File::delete(storage_path('framework/testing/ai-prompts.log'));

        $user = $this->makeUser();
        $tenant = Tenant::query()->create([
            'name' => 'Academia Global',
            'slug' => 'academia-global',
            'is_active' => true,
        ]);
        $this->seedCatalog();
        $this->seedPreviousWorkout($user);

        Http::fake([
            'https://api.openai.com/v1/vector_stores' => Http::response([
                'id' => 'vs_test_123',
                'name' => 'akdemia-workouts-global',
            ], 200),
            'https://api.openai.com/v1/files' => Http::response([
                'id' => 'file_test_123',
                'filename' => 'test-openai-workout-catalog.jsonl',
            ], 200),
            'https://api.openai.com/v1/vector_stores/vs_test_123/files' => Http::response([
                'id' => 'vsf_test_123',
                'status' => 'completed',
            ], 200),
            'https://api.openai.com/v1/vector_stores/vs_test_123/search' => Http::response([
                'data' => [
                    ['content' => [['type' => 'text', 'text' => json_encode(['remote_exercise_id' => '0009', 'localized_name_pt_br' => 'Supino reto com barra', 'workoutx_name' => 'barbell-bench-press', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'barbell'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]]],
                    ['content' => [['type' => 'text', 'text' => json_encode(['remote_exercise_id' => '0010', 'localized_name_pt_br' => 'Supino inclinado com halteres', 'workoutx_name' => 'incline-dumbbell-bench-press', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'dumbbell'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]]],
                    ['content' => [['type' => 'text', 'text' => json_encode(['remote_exercise_id' => '0011', 'localized_name_pt_br' => 'Crucifixo no cabo', 'workoutx_name' => 'cable-fly', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'cable'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]]],
                    ['content' => [['type' => 'text', 'text' => json_encode(['remote_exercise_id' => '0012', 'localized_name_pt_br' => 'Peck deck', 'workoutx_name' => 'pec-deck-fly', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'machine'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]]],
                    ['content' => [['type' => 'text', 'text' => json_encode(['remote_exercise_id' => '1160', 'localized_name_pt_br' => 'Caminhada inclinada', 'workoutx_name' => 'incline-treadmill-walk', 'focus' => 'cardio', 'body_part' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'treadmill'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]]],
                ],
            ], 200),
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_test_123',
                'model' => 'gpt-4o-mini',
                'output_text' => json_encode([
                    'weekly_plan' => [[
                        'day' => 'Segunda',
                        'focus' => 'Peito',
                        'exercises' => [
                            ['name' => 'Supino reto com barra', 'category' => 'specific', 'sets' => 4, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Controle o movimento.', 'steps' => ['Ajuste a pegada', 'Desca com controle', 'Empurre ate a extensao'], 'remote_exercise_id' => '0009', 'workoutx_name' => 'barbell-bench-press'],
                            ['name' => 'Supino inclinado com halteres', 'category' => 'specific', 'sets' => 3, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Mantenha estabilidade.', 'steps' => ['Sente e ajuste os halteres', 'Desca lentamente', 'Suba mantendo alinhamento'], 'remote_exercise_id' => '0010', 'workoutx_name' => 'incline-dumbbell-bench-press'],
                            ['name' => 'Crucifixo no cabo', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Evite balanco.', 'steps' => ['Posicione as polias', 'Aproxime as maos', 'Retorne controlando'], 'remote_exercise_id' => '0011', 'workoutx_name' => 'cable-fly'],
                            ['name' => 'Peck deck', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Mantenha escapulas firmes.', 'steps' => ['Ajuste o banco', 'Feche os bracos', 'Retorne sem soltar peso'], 'remote_exercise_id' => '0012', 'workoutx_name' => 'pec-deck-fly'],
                            ['name' => 'Caminhada inclinada', 'category' => 'cardio', 'sets' => 1, 'reps' => '15 min', 'rest' => '0s', 'notes' => 'Ritmo moderado.', 'steps' => ['Inicie em ritmo leve', 'Ajuste a inclinacao', 'Mantenha a respiracao ritmada'], 'remote_exercise_id' => '1160', 'workoutx_name' => 'incline-treadmill-walk'],
                        ],
                    ]],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'usage' => ['input_tokens' => 321, 'output_tokens' => 212, 'total_tokens' => 533],
            ], 200),
        ]);

        $result = app(AiService::class)->generateWorkout($user, $tenant);

        $this->assertSame('Peito', data_get($result, 'weekly_plan.0.focus'));
        $this->assertDatabaseCount('ai_vector_stores', 1);
        $this->assertDatabaseCount('ai_logs', 3);
        $this->assertInstanceOf(AiVectorStore::class, AiVectorStore::query()->first());
        $this->assertNull(AiVectorStore::query()->first()?->tenant_id);
        $this->assertSame('akdemia-workouts-global', AiVectorStore::query()->first()?->vector_store_name);
        $promptLogPath = storage_path('framework/testing/ai-prompts.log');
        $this->assertFileExists($promptLogPath);
        $this->assertStringContainsString('"operation":"generation"', (string) file_get_contents($promptLogPath));
        $this->assertStringContainsString('"operation":"vector_store_sync"', (string) file_get_contents($promptLogPath));

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.openai.com/v1/responses') {
                return false;
            }

            $payload = $request->data();
            $prompt = (string) data_get($payload, 'input.1.content.0.text', '');

            return data_get($payload, 'model') === 'gpt-4o-mini'
                && data_get($payload, 'tools.0.type') === 'file_search'
                && data_get($payload, 'tools.0.vector_store_ids.0') === 'vs_test_123'
                && data_get($payload, 'text.format.type') === 'json_schema'
                && data_get($payload, 'text.format.strict') === true
                && str_contains($prompt, 'Exercicios prioritarios recuperados')
                && str_contains($prompt, 'barbell-bench-press')
                && ! str_contains($prompt, 'Exercícios disponíveis:')
                && ! str_contains($prompt, '"peito":[{');
        });
    }

    public function test_generate_workout_falls_back_to_local_candidates_when_vector_search_fails(): void
    {
        config()->set('services.openai.api_key', 'openai-test-key');
        config()->set('services.openai.responses_model', 'gpt-4o-mini');
        config()->set('services.internal_catalog.storage_path', 'ai/test-openai-workout-catalog.json');
        config()->set('services.internal_catalog.vector_store_storage_path', 'ai/test-openai-workout-catalog.jsonl');
        Storage::fake('local');

        $user = $this->makeUser();
        $this->seedCatalog();

        Http::fake([
            'https://api.openai.com/v1/vector_stores' => Http::response(['id' => 'vs_test_123'], 200),
            'https://api.openai.com/v1/files' => Http::response(['id' => 'file_test_123'], 200),
            'https://api.openai.com/v1/vector_stores/vs_test_123/files' => Http::response(['id' => 'vsf_test_123', 'status' => 'completed'], 200),
            'https://api.openai.com/v1/vector_stores/vs_test_123/search' => Http::response(['error' => 'unavailable'], 500),
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_test_local_fallback',
                'model' => 'gpt-4o-mini',
                'output_text' => json_encode([
                    'weekly_plan' => [[
                        'day' => 'Segunda',
                        'focus' => 'Peito',
                        'exercises' => [
                            ['name' => 'Supino reto com barra', 'category' => 'specific', 'sets' => 4, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Controle o movimento.', 'steps' => ['Ajuste a pegada', 'Desca com controle', 'Empurre ate a extensao'], 'remote_exercise_id' => '0009', 'workoutx_name' => 'barbell-bench-press'],
                            ['name' => 'Supino inclinado com halteres', 'category' => 'specific', 'sets' => 3, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Mantenha estabilidade.', 'steps' => ['Sente e ajuste os halteres', 'Desca lentamente', 'Suba mantendo alinhamento'], 'remote_exercise_id' => '0010', 'workoutx_name' => 'incline-dumbbell-bench-press'],
                            ['name' => 'Crucifixo no cabo', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Evite balanco.', 'steps' => ['Posicione as polias', 'Aproxime as maos', 'Retorne controlando'], 'remote_exercise_id' => '0011', 'workoutx_name' => 'cable-fly'],
                            ['name' => 'Peck deck', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Mantenha escapulas firmes.', 'steps' => ['Ajuste o banco', 'Feche os bracos', 'Retorne sem soltar peso'], 'remote_exercise_id' => '0012', 'workoutx_name' => 'pec-deck-fly'],
                            ['name' => 'Caminhada inclinada', 'category' => 'cardio', 'sets' => 1, 'reps' => '15 min', 'rest' => '0s', 'notes' => 'Ritmo moderado.', 'steps' => ['Inicie em ritmo leve', 'Ajuste a inclinacao', 'Mantenha a respiracao ritmada'], 'remote_exercise_id' => '1160', 'workoutx_name' => 'incline-treadmill-walk'],
                        ],
                    ]],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ], 200),
        ]);

        app(AiService::class)->generateWorkout($user, null);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.openai.com/v1/responses') {
                return false;
            }

            $payload = $request->data();
            $prompt = (string) data_get($payload, 'input.1.content.0.text', '');

            return data_get($payload, 'tools', []) === []
                && str_contains($prompt, 'Modo de retrieval: local_fallback')
                && str_contains($prompt, 'barbell-bench-press');
        });
    }

    private function makeUser(): User
    {
        $user = User::factory()->create([
            'name' => 'Aluno Teste',
            'email' => 'aluno@example.com',
            'birth_date' => '1995-05-20',
            'gender' => 'male',
            'height' => 180,
            'weight' => 85,
            'goal' => 'hipertrofia',
        ]);

        PhysicalData::query()->create([
            'user_id' => $user->id,
            'activity_level' => 'moderate',
            'imc' => 26.2,
            'body_fat_percentage' => 18.5,
        ]);

        MedicalData::query()->create([
            'user_id' => $user->id,
            'injuries' => 'Nenhuma',
            'restrictions' => 'Nenhuma',
        ]);

        Preference::query()->create([
            'user_id' => $user->id,
            'training_frequency' => '1x por semana',
            'available_hours' => ['18:00'],
        ]);

        return $user;
    }

    private function seedCatalog(): void
    {
        foreach ([
            ['id' => '0009', 'localized' => 'Supino reto com barra', 'slug' => 'barbell-bench-press', 'name' => 'Barbell Bench Press', 'body' => 'chest', 'target' => 'pectorals', 'equipment' => 'barbell'],
            ['id' => '0010', 'localized' => 'Supino inclinado com halteres', 'slug' => 'incline-dumbbell-bench-press', 'name' => 'Incline Dumbbell Bench Press', 'body' => 'chest', 'target' => 'pectorals', 'equipment' => 'dumbbell'],
            ['id' => '0011', 'localized' => 'Crucifixo no cabo', 'slug' => 'cable-fly', 'name' => 'Cable Fly', 'body' => 'chest', 'target' => 'pectorals', 'equipment' => 'cable'],
            ['id' => '0012', 'localized' => 'Peck deck', 'slug' => 'pec-deck-fly', 'name' => 'Pec Deck Fly', 'body' => 'chest', 'target' => 'pectorals', 'equipment' => 'machine'],
            ['id' => '1160', 'localized' => 'Caminhada inclinada', 'slug' => 'incline-treadmill-walk', 'name' => 'Incline Treadmill Walk', 'body' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'treadmill'],
        ] as $exercise) {
            ExerciseMediaCache::query()->create([
                'remote_exercise_id' => $exercise['id'],
                'localized_name_pt_br' => $exercise['localized'],
                'workoutx_name' => $exercise['slug'],
                'query_name' => $exercise['name'],
                'payload' => [
                    'id' => $exercise['id'],
                    'name' => $exercise['name'],
                    'bodyPart' => $exercise['body'],
                    'target' => $exercise['target'],
                    'equipment' => $exercise['equipment'],
                ],
            ]);
        }
    }

    private function seedPreviousWorkout(User $user): void
    {
        Workout::query()->create([
            'tenant_id' => null,
            'user_id' => $user->id,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => [
                'weekly_plan' => [[
                    'day' => 'Segunda',
                    'focus' => 'Peito',
                    'exercises' => [[
                        'name' => 'Supino reto com barra',
                        'category' => 'specific',
                        'workoutx_name' => 'barbell-bench-press',
                        'remote_exercise_id' => '0009',
                    ]],
                ]],
            ],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);
    }
}
