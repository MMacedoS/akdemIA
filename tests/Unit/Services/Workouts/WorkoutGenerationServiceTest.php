<?php

namespace Tests\Unit\Services\Workouts;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use App\Services\AI\AiService;
use App\Services\AI\ValidationService;
use App\Services\Workouts\WorkoutGenerationService;
use App\Services\Workouts\WorkoutMediaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WorkoutGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_generate_stores_workout_without_requesting_diet(): void
    {
        $user = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Academia Teste',
            'slug' => 'academia-teste',
            'is_active' => true,
        ]);

        $tenant->users()->attach($user->id, [
            'role' => Role::STUDENT->value,
        ]);

        $aiWorkoutResponse = [
            'weekly_plan' => [
                [
                    'day' => 'Segunda',
                    'focus' => 'Costas',
                    'exercises' => [
                        [
                            'name' => 'Puxada frontal',
                            'category' => 'specific',
                            'sets' => 4,
                            'reps' => '8-12',
                            'rest' => '60s',
                            'notes' => 'Mantenha o tronco firme.',
                            'steps' => ['Ajuste a pegada', 'Puxe ate a linha do peito', 'Retorne de forma controlada'],
                            'workoutx_name' => 'barbell-bench-press',
                            'remote_exercise_id' => '0001',
                            'illustration_svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"></svg>',
                        ],
                    ],
                ],
            ],
        ];

        $validationService = Mockery::mock(ValidationService::class);
        $validationService->shouldReceive('validateUserForWorkout')->once()->with($user);
        $validationService->shouldReceive('safetyFlags')->once()->andReturn(['beginner' => true]);

        $aiService = Mockery::mock(AiService::class);
        $aiService->shouldReceive('workoutPromptVersion')->andReturn(AiService::WORKOUT_PROMPT_VERSION);
        $aiService->shouldReceive('generateRecommendations')->once()->with($user, $tenant)->andReturn([
            'recommendations' => ['Dormir pelo menos 7 horas por noite'],
            'cardio_plan' => [
                [
                    'type' => 'Caminhada',
                    'duration' => '20 minutos',
                    'frequency' => '3x por semana',
                ],
            ],
        ]);
        $aiService->shouldReceive('generateWorkout')->once()->with($user, $tenant, false, 'ajustar')->andReturn($aiWorkoutResponse);

        $enrichedWorkoutPlan = [
            'weekly_plan' => [
                [
                    'day' => 'Segunda',
                    'focus' => 'Costas',
                    'exercises' => [
                        [
                            'name' => 'Puxada frontal',
                            'category' => 'specific',
                            'sets' => 4,
                            'reps' => '8-12',
                            'rest' => '60s',
                            'notes' => 'Mantenha o tronco firme.',
                            'steps' => ['Ajuste a pegada', 'Puxe ate a linha do peito', 'Retorne de forma controlada'],
                            'workoutx_name' => 'barbell-bench-press',
                            'exercise_media_path' => 'exercises/barbell-bench-press.gif',
                            'exercise_media_url' => '/api/v1/workouts/exercises/media/barbell-bench-press',
                            'illustration_svg' => '',
                        ],
                    ],
                ],
            ],
        ];

        $workoutMediaService = Mockery::mock(WorkoutMediaService::class);
        $workoutMediaService->shouldReceive('enrichWorkoutPlan')->once()->with($aiWorkoutResponse)->andReturn($enrichedWorkoutPlan);

        $service = new WorkoutGenerationService($validationService, $aiService, $workoutMediaService);

        $workout = $service->generate($user, $tenant, 'ajustar');

        $this->assertSame([], $workout->meal_plan);
        $this->assertSame($enrichedWorkoutPlan, $workout->workout_plan);
        $this->assertSame(['Dormir pelo menos 7 horas por noite'], $workout->recommendations);
        $this->assertSame('Caminhada', data_get($workout->cardio_plan, '0.type'));
    }

    public function test_generate_returns_cached_legacy_workout_without_rewriting_payload(): void
    {
        config()->set('services.workoutx.enabled', false);

        $user = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Academia Teste',
            'slug' => 'academia-teste',
            'is_active' => true,
        ]);

        $tenant->users()->attach($user->id, [
            'role' => Role::STUDENT->value,
        ]);

        $legacyWorkout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => [
                'weekly_plan' => [
                    [
                        'day' => 'Segunda',
                        'focus' => 'Peito',
                        'exercises' => [
                            ['name' => 'Supino reto', 'category' => 'specific', 'sets' => 4, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Controle'],
                            ['name' => 'Crucifixo reto', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Controle'],
                            ['name' => 'Flexao', 'category' => 'specific', 'sets' => 3, 'reps' => '12', 'rest' => '45s', 'notes' => 'Controle'],
                            ['name' => 'Peck deck', 'category' => 'specific', 'sets' => 3, 'reps' => '12', 'rest' => '45s', 'notes' => 'Controle'],
                            ['name' => 'Caminhada leve', 'category' => 'cardio', 'sets' => 1, 'reps' => '15 min', 'rest' => '0s', 'notes' => 'Cardio'],
                        ],
                    ],
                ],
            ],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $validationService = Mockery::mock(ValidationService::class);
        $validationService->shouldNotReceive('validateWorkoutResponse');

        $aiService = Mockery::mock(AiService::class);
        $aiService->shouldReceive('workoutPromptVersion')->andReturn(AiService::WORKOUT_PROMPT_VERSION);
        $aiService->shouldNotReceive('generateWorkout');
        $aiService->shouldNotReceive('generateRecommendations');

        $workoutxConfigMarker = implode('-', [
            '0',
            '0',
            (string) config('services.workoutx.auth_mode', 'header'),
            substr(sha1((string) config('services.workoutx.api_base_url', '')), 0, 8),
            substr(sha1(AiService::WORKOUT_PROMPT_VERSION), 0, 8),
            '0',
        ]);

        $cacheKey = 'workout:v4-workoutx-media-refresh:' . $user->id . ':' . ($user->updated_at?->timestamp ?? 0) . '-0-0-0:workoutx:' . $workoutxConfigMarker . ':tenant:' . $tenant->id;
        Cache::put($cacheKey, $legacyWorkout->id, 3600);

        $service = new WorkoutGenerationService($validationService, $aiService, new WorkoutMediaService());

        $workout = $service->generate($user, $tenant);

        $this->assertSame($legacyWorkout->workout_plan, $workout->workout_plan);
    }

    public function test_generate_payload_returns_enriched_data_without_persisting_temporary_workout(): void
    {
        $user = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Academia Teste',
            'slug' => 'academia-payload',
            'is_active' => true,
        ]);

        $tenant->users()->attach($user->id, [
            'role' => Role::STUDENT->value,
        ]);

        $aiWorkoutResponse = [
            'weekly_plan' => [
                [
                    'day' => 'Segunda',
                    'focus' => 'Costas',
                    'exercises' => [
                        [
                            'name' => 'Puxada frontal',
                            'category' => 'specific',
                            'sets' => 4,
                            'reps' => '8-12',
                            'rest' => '60s',
                            'notes' => 'Controle a escapula.',
                            'steps' => ['Ajuste a pegada', 'Puxe ao peito'],
                            'workoutx_name' => 'lat-pulldown',
                            'remote_exercise_id' => 'lat-001',
                        ],
                    ],
                ],
            ],
        ];

        $validationService = Mockery::mock(ValidationService::class);
        $validationService->shouldReceive('validateUserForWorkout')->once()->with($user);
        $validationService->shouldReceive('safetyFlags')->once()->andReturn(['shoulder_sensitive' => true]);

        $aiService = Mockery::mock(AiService::class);
        $aiService->shouldReceive('generateRecommendations')->once()->with($user, $tenant)->andReturn([
            'recommendations' => ['Mantenha aquecimento de ombros antes das sessoes.'],
            'cardio_plan' => [
                [
                    'type' => 'Caminhada',
                    'duration' => '15 minutos',
                    'frequency' => '2x por semana',
                ],
            ],
        ]);
        $aiService->shouldReceive('generateWorkout')->once()->with($user, $tenant, false, null)->andReturn($aiWorkoutResponse);

        $workoutMediaService = Mockery::mock(WorkoutMediaService::class);
        $workoutMediaService->shouldReceive('enrichWorkoutPlan')->once()->with($aiWorkoutResponse)->andReturn($aiWorkoutResponse);

        $service = new WorkoutGenerationService($validationService, $aiService, $workoutMediaService);

        $payload = $service->generatePayload($user, $tenant);

        $this->assertSame($aiWorkoutResponse, $payload['workout_plan']);
        $this->assertSame(['Mantenha aquecimento de ombros antes das sessoes.'], $payload['recommendations']);
        $this->assertSame(['shoulder_sensitive' => true], $payload['safety_flags']);
        $this->assertSame(0, Workout::query()->count());
    }
}
