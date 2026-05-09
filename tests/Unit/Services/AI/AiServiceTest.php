<?php

namespace Tests\Unit\Services\AI;

use App\Models\MedicalData\MedicalData;
use App\Models\PhysicalData\PhysicalData;
use App\Models\Preferences\Preference;
use App\Models\User;
use App\Models\Workout\ExerciseMediaCache;
use App\Services\AI\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_workout_includes_local_catalog_and_remote_id_rules_in_prompt(): void
    {
        config()->set('services.openai.api_key', 'openai-test-key');
        config()->set('services.openai.model', 'gpt-4o-mini');

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
            'training_frequency' => '5x por semana',
            'available_hours' => ['18:00'],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0009',
            'localized_name_pt_br' => 'Supino reto com barra',
            'workoutx_name' => 'barbell-bench-press',
            'query_name' => 'Barbell Bench Press',
            'payload' => [
                'id' => '0009',
                'name' => 'Barbell Bench Press',
                'bodyPart' => 'chest',
                'target' => 'pectorals',
                'equipment' => 'barbell',
            ],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '1160',
            'workoutx_name' => 'incline-treadmill-walk',
            'query_name' => 'Incline Treadmill Walk',
            'payload' => [
                'id' => '1160',
                'name' => 'Incline Treadmill Walk',
                'bodyPart' => 'cardio',
                'target' => 'cardiovascular system',
                'equipment' => 'treadmill',
            ],
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'weekly_plan' => [
                                [
                                    'day' => 'Segunda',
                                    'focus' => 'Peito',
                                    'exercises' => [],
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ]],
            ], 200),
        ]);

        app(AiService::class)->generateWorkout($user, null);

        Http::assertSent(function (Request $request): bool {
            $prompt = (string) ($request['messages'][1]['content'] ?? '');

            return $request->method() === 'POST'
                && $request->url() === 'https://api.openai.com/v1/chat/completions'
                && str_contains($prompt, 'CATALOGO LOCAL ENXUTO POR FOCO MUSCULAR (OBRIGATORIO)')
                && str_contains($prompt, 'limitado a 12 exercicios por grupo')
                && str_contains($prompt, 'Use SOMENTE exercicios presentes neste catalogo local.')
                && str_contains($prompt, '"peito":[{"id":"0009"')
                && str_contains($prompt, '"localized_name_pt_br":"Supino reto com barra"')
                && str_contains($prompt, '"workoutx_name":"barbell-bench-press"')
                && str_contains($prompt, '"cardio":[{"id":"1160"')
                && str_contains($prompt, '"workoutx_name":"incline-treadmill-walk"')
                && str_contains($prompt, '"remote_exercise_id": "0043"')
                && str_contains($prompt, 'O campo name deve ficar em pt-BR. Quando localized_name_pt_br estiver preenchido, use exatamente esse valor.')
                && str_contains($prompt, 'Cada exercicio possui remote_exercise_id real do catalogo local?');
        });
    }
}
