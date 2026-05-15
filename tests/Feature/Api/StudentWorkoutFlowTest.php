<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Jobs\GenerateWorkoutJob;
use App\Models\Credits\CreditTransaction;
use App\Models\Tenant\Plan;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSubscription;
use App\Models\User;
use App\Models\Workout\Workout;
use App\Notifications\WorkoutGenerationFinishedNotification;
use App\Services\AI\AiService;
use App\Services\Credits\CreditService;
use App\Services\System\SystemSettingsRuntimeService;
use App\Services\Tenant\Auth\TenantAuthService;
use App\Services\Workouts\WorkoutGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class StudentWorkoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $slug = 'academia-teste'): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => 'Academia Teste',
            'slug' => $slug,
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'name' => 'Plano Teste ' . $slug,
            'price' => 99.90,
            'max_students' => 100,
            'max_trainers' => 10,
            'ai_limit' => 1000,
            'features' => [],
        ]);

        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'stripe_subscription_id' => null,
            'status' => 'active',
            'ai_usage' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        return $tenant;
    }

    private function makeSpecificExercise(
        string $name,
        string $workoutxName,
        string $remoteExerciseId,
        int $sets = 4,
        string $reps = '8-12',
        string $rest = '60s',
    ): array {
        return [
            'name' => $name,
            'category' => 'specific',
            'sets' => $sets,
            'reps' => $reps,
            'rest' => $rest,
            'notes' => 'Executar com controle e tecnica estavel.',
            'steps' => ['Organize a postura inicial', 'Execute com amplitude segura'],
            'remote_exercise_id' => $remoteExerciseId,
            'workoutx_name' => $workoutxName,
        ];
    }

    private function makeCardioExercise(string $name, string $workoutxName, string $remoteExerciseId): array
    {
        return [
            'name' => $name,
            'category' => 'cardio',
            'sets' => 1,
            'reps' => '12-20 min',
            'rest' => '0s',
            'notes' => 'Mantenha intensidade moderada e respiracao controlada.',
            'steps' => ['Inicie leve', 'Mantenha constancia no bloco principal'],
            'remote_exercise_id' => $remoteExerciseId,
            'workoutx_name' => $workoutxName,
        ];
    }

    private function makeWorkoutDay(string $day, string $focus, array $exercises): array
    {
        return [
            'day' => $day,
            'focus' => $focus,
            'exercises' => $exercises,
        ];
    }

    public function test_student_can_list_current_workout_and_history_from_api(): void
    {
        $tenant = $this->createTenant();

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
        ]);

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        $olderWorkout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'error',
            'request_status' => 'inactive',
            'workout_plan' => ['weekly_plan' => []],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => ['generation_error' => 'falha'],
        ]);

        $oldWorkout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'inactive',
            'workout_plan' => ['weekly_plan' => [['day' => 'sunday']]],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $recentInactiveWorkout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'inactive',
            'workout_plan' => ['weekly_plan' => [['day' => 'tuesday']]],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $currentWorkout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => [
                'weekly_plan' => [['day' => 'monday']],
                'quality_scores' => [
                    'variation_score' => 80,
                    'fatigue_score' => 76,
                    'novelty_score' => 82,
                    'biomechanical_balance' => 79,
                    'recovery_score' => 77,
                ],
                'generation_insights' => [
                    'statistics' => [
                        'training_days' => 4,
                        'specific_exercises' => 16,
                        'cardio_blocks' => 4,
                    ],
                    'references' => ['Historico recente com excesso de empurradas horizontais.'],
                    'improvements' => ['A semana foi reequilibrada com maior presenca de puxadas verticais e remadas de suporte.'],
                ],
            ],
            'meal_plan' => [['meal' => 'breakfast']],
            'recommendations' => ['sleep'],
            'cardio_plan' => ['walk'],
            'safety_flags' => [],
        ]);

        $token = app(TenantAuthService::class)->generateTenantToken($student, $tenant);

        $this->getJson('/api/v1/students/workouts', [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk()
            ->assertJsonPath('current_workout.id', $currentWorkout->id)
            ->assertJsonPath('current_workout.workout_plan.weekly_plan.0.day', 'monday')
            ->assertJsonPath('current_workout.insights.statistics.training_days', 4)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('statistics.recent_workouts', 3)
            ->assertJsonPath('statistics.training_days_total', 6)
            ->assertJsonPath('statistics.references.0', 'Historico recente com excesso de empurradas horizontais.')
            ->assertJsonPath('data.0.id', $currentWorkout->id)
            ->assertJsonPath('data.1.id', $recentInactiveWorkout->id)
            ->assertJsonPath('data.2.id', $oldWorkout->id);

        $this->assertNotSame($olderWorkout->id, data_get($this->getJson('/api/v1/students/workouts', [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ])->json(), 'data.2.id'));
    }

    public function test_student_can_generate_workout_from_student_api_flow(): void
    {
        Queue::fake();

        $tenant = $this->createTenant('academia-geracao');

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
            'credits_balance' => 8,
        ]);

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        $previousWorkout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => ['weekly_plan' => [['day' => 'friday']]],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $token = app(TenantAuthService::class)->generateTenantToken($student, $tenant);

        $response = $this->postJson('/api/v1/students/workout/generate', [], [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ]);

        $workoutId = (int) $response->json('data.id');

        $response->assertAccepted()
            ->assertJsonPath('message', 'Geracao do treino iniciada.')
            ->assertJsonPath('credits_balance', 3)
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.user_id', $student->id);

        Queue::assertPushed(GenerateWorkoutJob::class, function (GenerateWorkoutJob $job) use ($student, $tenant, $workoutId): bool {
            return $job->userId === $student->id
                && $job->tenantId === $tenant->id
                && $job->workoutId === $workoutId;
        });

        $this->assertSame('inactive', (string) $previousWorkout->fresh()->request_status);
        $this->assertSame(3, $student->fresh()->credits_balance);
    }

    public function test_student_api_blocks_new_generation_for_one_week_after_failure(): void
    {
        Queue::fake();
        Notification::fake();

        $tenant = $this->createTenant('academia-bloqueio-api');

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
            'credits_balance' => 8,
        ]);

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        $token = app(TenantAuthService::class)->generateTenantToken($student, $tenant);

        $this->mock(WorkoutGenerationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generatePayload')
                ->once()
                ->andThrow(new \RuntimeException('Weekly plan exceeds hinge frequency recovery threshold.'));
        });

        $response = $this->postJson('/api/v1/students/workout/generate', [], [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ]);

        $workoutId = (int) $response->json('data.id');
        $capturedJob = null;

        $response->assertAccepted();

        Queue::assertPushed(GenerateWorkoutJob::class, function (GenerateWorkoutJob $job) use ($workoutId, &$capturedJob): bool {
            $capturedJob = $job;

            return $job->workoutId === $workoutId;
        });

        $this->assertInstanceOf(GenerateWorkoutJob::class, $capturedJob);

        $capturedJob->handle(
            app(WorkoutGenerationService::class),
            app(SystemSettingsRuntimeService::class),
            app(CreditService::class),
        );

        $this->assertDatabaseMissing('workouts', ['id' => $workoutId]);
        $this->assertSame(8, $student->fresh()->credits_balance);

        $refundTransaction = CreditTransaction::query()
            ->where('type', 'refund_workout_error')
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame('O plano semanal excedeu o limite de recuperacao para exercicios de hinge.', data_get($refundTransaction->metadata, 'generation_block.reason'));

        $blockedResponse = $this->postJson('/api/v1/students/workout/generate', [], [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ]);

        $blockedResponse->assertStatus(422)
            ->assertJsonPath('message', fn(string $message): bool => str_contains($message, 'temporariamente bloqueada') && str_contains($message, '7 dias'));
    }

    public function test_student_api_generation_flow_completes_job_for_joao_case(): void
    {
        Queue::fake();
        Notification::fake();

        $tenant = $this->createTenant('academia-joao');

        $student = $this->mockCreateUserTotal([
            'name' => 'Joao',
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
            'credits_balance' => 8,
            'goal' => 'hipertrofia',
            'birth_date' => '1994-04-10',
            'gender' => 'male',
            'height' => 178,
            'weight' => 82,
        ]);

        $student->physicalData()->update([
            'activity_level' => 'moderate',
            'imc' => 25.9,
        ]);

        $student->preference()->update([
            'training_frequency' => '5x por semana',
        ]);

        $student->medicalData()->update([
            'injuries' => 'Leve desconforto anterior no ombro direito.',
            'restrictions' => 'Evitar excesso de empurradas horizontais pesadas.',
        ]);

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        $previousActiveWorkout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => ['weekly_plan' => [['day' => 'Sexta']]],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'inactive',
            'workout_plan' => [
                'weekly_plan' => [
                    $this->makeWorkoutDay('Segunda', 'Peito', [
                        $this->makeSpecificExercise('Supino reto com barra', 'barbell-bench-press', 'bench-barbell', 4),
                        $this->makeSpecificExercise('Supino reto com barra', 'barbell-bench-press', 'bench-barbell', 4),
                        $this->makeSpecificExercise('Crucifixo reto', 'dumbbell-fly', 'fly-dumbbell', 3, '10-12', '45s'),
                        $this->makeSpecificExercise('Remada sentada', 'cable-row', 'row-seated', 3),
                        $this->makeCardioExercise('Caminhada moderada', 'treadmill-walk', 'cardio-walk-1'),
                    ]),
                ],
            ],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
            'created_at' => now()->subWeeks(1),
            'updated_at' => now()->subWeeks(1),
        ]);

        Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'inactive',
            'workout_plan' => [
                'weekly_plan' => [
                    $this->makeWorkoutDay('Quarta', 'Peito e Ombros', [
                        $this->makeSpecificExercise('Supino reto com barra', 'barbell-bench-press', 'bench-barbell', 4),
                        $this->makeSpecificExercise('Supino inclinado com halteres', 'incline-dumbbell-bench-press', 'bench-incline-dumbbell', 3),
                        $this->makeSpecificExercise('Desenvolvimento com barra', 'barbell-shoulder-press', 'shoulder-press-barbell', 3),
                        $this->makeSpecificExercise('Peck deck', 'pec-deck-fly', 'pec-deck', 3, '10-12', '45s'),
                        $this->makeCardioExercise('Bicicleta leve', 'stationary-bike', 'cardio-bike-1'),
                    ]),
                ],
            ],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
            'created_at' => now()->subWeeks(2),
            'updated_at' => now()->subWeeks(2),
        ]);

        $generatedPlan = [
            'weekly_plan' => [
                $this->makeWorkoutDay('Segunda', 'Peito e Triceps', [
                    $this->makeSpecificExercise('Supino maquina convergente', 'converging-machine-chest-press', 'bench-machine-convergent', 4),
                    $this->makeSpecificExercise('Supino inclinado com halteres', 'incline-dumbbell-bench-press', 'bench-incline-dumbbell', 3),
                    $this->makeSpecificExercise('Crucifixo no cabo', 'cable-fly', 'fly-cable', 3, '10-12', '45s'),
                    $this->makeSpecificExercise('Triceps corda', 'cable-rope-pushdown', 'triceps-rope', 3, '10-12', '45s'),
                    $this->makeCardioExercise('Caminhada moderada', 'treadmill-walk', 'cardio-walk-2'),
                ]),
                $this->makeWorkoutDay('Terca', 'Costas e Biceps', [
                    $this->makeSpecificExercise('Puxada alta aberta', 'lat-pulldown', 'lat-pulldown-wide', 4),
                    $this->makeSpecificExercise('Remada com apoio no peito', 'chest-supported-row', 'row-supported', 4),
                    $this->makeSpecificExercise('Pulldown bracos estendidos', 'straight-arm-pulldown', 'straight-arm-pulldown', 3, '10-12', '45s'),
                    $this->makeSpecificExercise('Rosca no cabo', 'cable-biceps-curl', 'biceps-cable', 3, '10-12', '45s'),
                    $this->makeCardioExercise('Bicicleta moderada', 'stationary-bike', 'cardio-bike-2'),
                ]),
                $this->makeWorkoutDay('Quarta', 'Pernas A', [
                    $this->makeSpecificExercise('Leg press', 'leg-press', 'leg-press', 4),
                    $this->makeSpecificExercise('Agachamento goblet', 'goblet-squat', 'goblet-squat', 3),
                    $this->makeSpecificExercise('Avanco andando', 'walking-lunge', 'walking-lunge', 3, '10-14', '45s'),
                    $this->makeSpecificExercise('Prancha', 'plank', 'plank', 3, '30-45 s', '30s'),
                    $this->makeCardioExercise('Eliptico leve', 'elliptical-trainer', 'cardio-elliptical-1'),
                ]),
                $this->makeWorkoutDay('Quinta', 'Costas e Deltoides Posteriores', [
                    $this->makeSpecificExercise('Barra assistida', 'assisted-pull-up', 'pull-up-assisted', 4),
                    $this->makeSpecificExercise('Remada sentada', 'cable-row', 'row-seated', 4),
                    $this->makeSpecificExercise('Crucifixo invertido maquina', 'reverse-pec-deck-fly', 'rear-delt-fly', 3, '10-14', '45s'),
                    $this->makeSpecificExercise('Elevacao lateral no cabo', 'cable-lateral-raise', 'lateral-raise-cable', 3, '10-14', '45s'),
                    $this->makeCardioExercise('Caminhada inclinada', 'incline-treadmill-walk', 'cardio-walk-3'),
                ]),
                $this->makeWorkoutDay('Sexta', 'Pernas B', [
                    $this->makeSpecificExercise('Levantamento romeno', 'romanian-deadlift', 'romanian-deadlift', 4),
                    $this->makeSpecificExercise('Hip thrust', 'barbell-hip-thrust', 'hip-thrust', 4),
                    $this->makeSpecificExercise('Mesa flexora', 'lying-leg-curl', 'leg-curl', 3, '10-14', '45s'),
                    $this->makeSpecificExercise('Woodchop no cabo', 'cable-woodchop', 'woodchop', 3, '10-14', '30s'),
                    $this->makeCardioExercise('Bicicleta leve', 'stationary-bike', 'cardio-bike-3'),
                ]),
            ],
            'quality_scores' => [
                'variation_score' => 88,
                'fatigue_score' => 84,
                'novelty_score' => 91,
                'biomechanical_balance' => 89,
                'recovery_score' => 86,
            ],
        ];

        $this->mock(AiService::class, function (MockInterface $mock) use ($student, $tenant, $generatedPlan): void {
            $mock->shouldReceive('workoutPromptVersion')->andReturn(AiService::WORKOUT_PROMPT_VERSION);
            $mock->shouldReceive('generateRecommendations')->once()->withArgs(function (User $user, Tenant $contextTenant) use ($student, $tenant): bool {
                return $user->is($student) && $contextTenant->is($tenant);
            })->andReturn([
                'recommendations' => [
                    'Priorize aquecimento escapular antes dos dias de membros superiores.',
                    'Reduza carga se houver desconforto anterior no ombro.',
                ],
                'cardio_plan' => [
                    ['type' => 'Caminhada', 'duration' => '15 minutos', 'frequency' => '3x por semana'],
                ],
            ]);
            $mock->shouldReceive('generateWorkout')->once()->withArgs(function (User $user, Tenant $contextTenant, bool $conservativeMode, ?string $adjustmentRequest) use ($student, $tenant): bool {
                return $user->is($student)
                    && $contextTenant->is($tenant)
                    && $conservativeMode === false
                    && $adjustmentRequest === null;
            })->andReturn($generatedPlan);
        });

        $token = app(TenantAuthService::class)->generateTenantToken($student, $tenant);

        $response = $this->postJson('/api/v1/students/workout/generate', [], [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ]);

        $workoutId = (int) $response->json('data.id');
        $capturedJob = null;

        $response->assertAccepted()
            ->assertJsonPath('message', 'Geracao do treino iniciada.')
            ->assertJsonPath('credits_balance', 3)
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.user_id', $student->id)
            ->assertJsonPath('data.tenant_id', $tenant->id);

        Queue::assertPushed(GenerateWorkoutJob::class, function (GenerateWorkoutJob $job) use ($student, $tenant, $workoutId, &$capturedJob): bool {
            $capturedJob = $job;

            return $job->userId === $student->id
                && $job->tenantId === $tenant->id
                && $job->workoutId === $workoutId
                && $job->requestedByUserId === $student->id;
        });

        $this->assertInstanceOf(GenerateWorkoutJob::class, $capturedJob);
        $this->assertSame('inactive', (string) $previousActiveWorkout->fresh()->request_status);
        $this->assertSame(3, $student->fresh()->credits_balance);

        $capturedJob->handle(
            app(\App\Services\Workouts\WorkoutGenerationService::class),
            app(SystemSettingsRuntimeService::class),
            app(\App\Services\Credits\CreditService::class),
        );

        $generatedWorkout = Workout::query()->findOrFail($workoutId);

        $this->assertSame('done', (string) $generatedWorkout->status);
        $this->assertSame('active', (string) $generatedWorkout->request_status);
        $this->assertCount(5, data_get($generatedWorkout->workout_plan, 'weekly_plan', []));
        $this->assertSame('Supino maquina convergente', data_get($generatedWorkout->workout_plan, 'weekly_plan.0.exercises.0.name'));
        $this->assertSame('converging-machine-chest-press', data_get($generatedWorkout->workout_plan, 'weekly_plan.0.exercises.0.workoutx_name'));
        $this->assertSame('Puxada alta aberta', data_get($generatedWorkout->workout_plan, 'weekly_plan.1.exercises.0.name'));
        $this->assertSame('Costas e Deltoides Posteriores', data_get($generatedWorkout->workout_plan, 'weekly_plan.3.focus'));
        $this->assertSame(['Caminhada'], array_values(array_filter(array_map(static fn(array $item): ?string => $item['type'] ?? null, $generatedWorkout->cardio_plan))));
        $this->assertSame(false, data_get($generatedWorkout->safety_flags, 'severe_injury'));
        $this->assertSame(false, data_get($generatedWorkout->safety_flags, 'high_imc'));
        $this->assertSame(false, data_get($generatedWorkout->safety_flags, 'beginner'));

        Notification::assertSentTo($student, WorkoutGenerationFinishedNotification::class);
    }

    public function test_student_can_change_workout_status_from_api_and_activation_consumes_credits(): void
    {
        $tenant = $this->createTenant('academia-status');

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
            'credits_balance' => 8,
        ]);

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        $previousWorkout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => ['weekly_plan' => [['day' => 'monday']]],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $targetWorkout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'inactive',
            'workout_plan' => ['weekly_plan' => [['day' => 'tuesday']]],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $token = app(TenantAuthService::class)->generateTenantToken($student, $tenant);

        $this->postJson('/api/v1/workouts/change-status/' . $targetWorkout->id, [
            'request_status' => 'active',
        ], [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk()
            ->assertJsonPath('message', 'Treino ativado com sucesso.')
            ->assertJsonPath('credits_balance', 6)
            ->assertJsonPath('data.id', $targetWorkout->id)
            ->assertJsonPath('data.request_status', 'active');

        $this->assertSame('inactive', (string) $previousWorkout->fresh()->request_status);
        $this->assertSame('active', (string) $targetWorkout->fresh()->request_status);
        $this->assertSame(6, $student->fresh()->credits_balance);

        $this->postJson('/api/v1/workouts/change-status/' . $targetWorkout->id, [
            'request_status' => 'inactive',
        ], [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk()
            ->assertJsonPath('message', 'Treino inativado com sucesso.')
            ->assertJsonPath('credits_balance', 6)
            ->assertJsonPath('data.request_status', 'inactive');

        $this->assertSame('inactive', (string) $targetWorkout->fresh()->request_status);
    }

    public function test_student_can_regenerate_workout_from_student_api_flow(): void
    {
        Queue::fake();

        $tenant = $this->createTenant('academia-refazer');

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
            'credits_balance' => 8,
        ]);

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        $currentWorkout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => ['weekly_plan' => [['day' => 'wednesday']]],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $token = app(TenantAuthService::class)->generateTenantToken($student, $tenant);

        $response = $this->postJson('/api/v1/students/workouts/' . $currentWorkout->id . '/regenerate', [
            'adjustment_request' => 'Trocar por treino com menos impacto no joelho.',
        ], [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ]);

        $workoutId = (int) $response->json('data.id');

        $response->assertAccepted()
            ->assertJsonPath('message', 'Refazer treino iniciado.')
            ->assertJsonPath('credits_balance', 5)
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.request_status', 'active')
            ->assertJsonPath('data.regeneration_request', 'Trocar por treino com menos impacto no joelho.')
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.user_id', $student->id);

        Queue::assertPushed(GenerateWorkoutJob::class, function (GenerateWorkoutJob $job) use ($student, $tenant, $currentWorkout, $workoutId): bool {
            return $job->userId === $student->id
                && $job->tenantId === $tenant->id
                && $job->workoutId === $workoutId
                && $job->requestedByUserId === $student->id
                && $job->adjustmentRequest === 'Trocar por treino com menos impacto no joelho.';
        });

        $this->assertSame('inactive', (string) $currentWorkout->fresh()->request_status);
        $this->assertSame(5, $student->fresh()->credits_balance);
    }

    public function test_standalone_student_can_list_current_workout_and_history_from_student_api(): void
    {
        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
        ]);

        $olderWorkout = Workout::query()->create([
            'tenant_id' => null,
            'user_id' => $student->id,
            'status' => 'error',
            'request_status' => 'inactive',
            'workout_plan' => ['weekly_plan' => []],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => ['generation_error' => 'falha'],
        ]);

        $oldWorkout = Workout::query()->create([
            'tenant_id' => null,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'inactive',
            'workout_plan' => ['weekly_plan' => [['day' => 'saturday']]],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $recentInactiveWorkout = Workout::query()->create([
            'tenant_id' => null,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'inactive',
            'workout_plan' => ['weekly_plan' => [['day' => 'friday']]],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $currentWorkout = Workout::query()->create([
            'tenant_id' => null,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => ['weekly_plan' => [['day' => 'monday']]],
            'meal_plan' => [['meal' => 'breakfast']],
            'recommendations' => ['sleep'],
            'cardio_plan' => ['walk'],
            'safety_flags' => [],
        ]);

        $token = app(TenantAuthService::class)->generateStandaloneToken($student);

        $this->getJson('/api/v1/students/workouts', [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()
            ->assertJsonPath('current_workout.id', $currentWorkout->id)
            ->assertJsonPath('current_workout.tenant_id', null)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $currentWorkout->id)
            ->assertJsonPath('data.1.id', $recentInactiveWorkout->id)
            ->assertJsonPath('data.2.id', $oldWorkout->id);

        $this->assertNotSame($olderWorkout->id, data_get($this->getJson('/api/v1/students/workouts', [
            'Authorization' => 'Bearer ' . $token,
        ])->json(), 'data.2.id'));

        $this->getJson('/api/v1/students/workout', [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()
            ->assertJsonPath('data.id', $currentWorkout->id)
            ->assertJsonPath('data.tenant_id', null);
    }

    public function test_standalone_student_can_generate_workout_from_student_api_flow(): void
    {
        Queue::fake();

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
            'credits_balance' => 8,
        ]);

        $token = app(TenantAuthService::class)->generateStandaloneToken($student);

        $response = $this->postJson('/api/v1/students/workout/generate', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $workoutId = (int) $response->json('data.id');

        $response->assertAccepted()
            ->assertJsonPath('message', 'Geracao do treino iniciada.')
            ->assertJsonPath('credits_balance', 3)
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.tenant_id', null)
            ->assertJsonPath('data.user_id', $student->id);

        Queue::assertPushed(GenerateWorkoutJob::class, function (GenerateWorkoutJob $job) use ($student, $workoutId): bool {
            return $job->userId === $student->id
                && $job->tenantId === null
                && $job->workoutId === $workoutId;
        });

        $this->assertSame(3, $student->fresh()->credits_balance);
    }

    public function test_standalone_student_can_regenerate_workout_from_student_api_flow(): void
    {
        Queue::fake();

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
            'credits_balance' => 8,
        ]);

        $currentWorkout = Workout::query()->create([
            'tenant_id' => null,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => ['weekly_plan' => [['day' => 'thursday']]],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $token = app(TenantAuthService::class)->generateStandaloneToken($student);

        $response = $this->postJson('/api/v1/students/workouts/' . $currentWorkout->id . '/regenerate', [
            'adjustment_request' => 'Quero um treino mais rapido para dias corridos.',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $workoutId = (int) $response->json('data.id');

        $response->assertAccepted()
            ->assertJsonPath('message', 'Refazer treino iniciado.')
            ->assertJsonPath('credits_balance', 5)
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.tenant_id', null)
            ->assertJsonPath('data.user_id', $student->id)
            ->assertJsonPath('data.regeneration_request', 'Quero um treino mais rapido para dias corridos.');

        Queue::assertPushed(GenerateWorkoutJob::class, function (GenerateWorkoutJob $job) use ($student, $currentWorkout, $workoutId): bool {
            return $job->userId === $student->id
                && $job->tenantId === null
                && $job->workoutId === $workoutId
                && $job->requestedByUserId === $student->id
                && $job->adjustmentRequest === 'Quero um treino mais rapido para dias corridos.';
        });

        $this->assertSame('inactive', (string) $currentWorkout->fresh()->request_status);
        $this->assertSame(5, $student->fresh()->credits_balance);
    }

    public function test_student_api_rejects_student_with_wrong_tenant_token_context(): void
    {
        $linkedTenant = $this->createTenant('tenant-vinculado');
        $wrongTenant = $this->createTenant('tenant-errado');

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
        ]);

        $linkedTenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        $token = app(TenantAuthService::class)->generateTenantToken($student, $wrongTenant);

        $this->getJson('/api/v1/students/workout', [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $wrongTenant->slug,
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid token claims.');
    }

    public function test_student_api_returns_forbidden_for_non_student_user(): void
    {
        $tenant = $this->createTenant('tenant-trainer');

        $trainer = User::factory()->create([
            'profile_type' => Role::TRAINER->value,
            'is_active' => true,
        ]);

        $tenant->users()->attach($trainer->id, ['role' => Role::TRAINER->value]);

        $token = app(TenantAuthService::class)->generateTenantToken($trainer, $tenant);

        $this->getJson('/api/v1/students/workout', [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }
}
