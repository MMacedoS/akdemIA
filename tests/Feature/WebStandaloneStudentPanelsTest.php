<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\GenerateWorkoutJob;
use App\Models\Workout\Workout;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantStudentTraineeLink;
use App\Models\User;
use App\Notifications\WorkoutGenerationFinishedNotification;
use App\Services\AI\AiService;
use App\Services\Credits\CreditService;
use App\Services\System\SystemSettingsRuntimeService;
use App\Services\Workouts\WorkoutGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class WebStandaloneStudentPanelsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_admin_panel_lists_and_shows_standalone_students_visible_through_tenant_trainees(): void
    {
        [$tenant, $admin, $trainer] = $this->createTenantContext();

        $student = User::factory()->create([
            'name' => 'Aluno Standalone',
            'email' => 'standalone@admin.test',
            'profile_type' => Role::STUDENT->value,
        ]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $trainer->id,
            'linked_by_user_id' => $admin->id,
            'note' => null,
        ]);

        $indexResponse = $this->actingAs($admin)
            ->withSession(['tenant_id' => $tenant->id])
            ->get(route('admin.students.index'));

        $indexResponse->assertOk()
            ->assertSeeText('Aluno Standalone')
            ->assertViewHas('metrics', fn(array $metrics) => $metrics['total'] === 1);

        $showResponse = $this->actingAs($admin)
            ->withSession(['tenant_id' => $tenant->id])
            ->get(route('admin.students.show', $student->id));

        $showResponse->assertOk()
            ->assertSeeText('Aluno Standalone');

        $dashboardResponse = $this->actingAs($admin)
            ->withSession(['tenant_id' => $tenant->id])
            ->get(route('admin.dashboard'));

        $dashboardResponse->assertOk()
            ->assertViewHas('summary', fn(array $summary) => $summary['total_students'] === 1);
    }

    public function test_admin_can_create_standalone_student_from_panel_without_tenant_user_link(): void
    {
        [$tenant, $admin, $trainer] = $this->createTenantContext();

        $response = $this->actingAs($admin)
            ->withSession(['tenant_id' => $tenant->id])
            ->post(route('admin.students.store'), [
                'name' => 'Aluno Painel',
                'email' => 'painel@admin.test',
                'password' => 'password123',
                'goal' => 'hipertrofia',
                'trainee_user_id' => $trainer->id,
            ]);

        $student = User::query()->where('email', 'painel@admin.test')->firstOrFail();

        $response->assertRedirect(route('admin.students.index'));
        $this->assertDatabaseMissing('tenant_user', [
            'user_id' => $student->id,
            'role' => Role::STUDENT->value,
        ]);
        $this->assertDatabaseHas('tenant_student_trainee_links', [
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $trainer->id,
        ]);
    }

    public function test_trainer_panel_lists_and_generates_workout_for_standalone_students(): void
    {
        Queue::fake();

        [$tenant,, $trainer] = $this->createTenantContext();

        $student = User::factory()->create([
            'name' => 'Aluno Trainer',
            'email' => 'standalone@trainer.test',
            'profile_type' => Role::STUDENT->value,
        ]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => $tenant->id,
            'student_user_id' => $student->id,
            'trainee_user_id' => $trainer->id,
            'linked_by_user_id' => $trainer->id,
            'note' => null,
        ]);

        $dashboardResponse = $this->actingAs($trainer)
            ->withSession(['tenant_id' => $tenant->id])
            ->get(route('trainer.dashboard'));

        $dashboardResponse->assertOk()
            ->assertViewHas('summary', fn(array $summary) => $summary['students'] === 1);

        $indexResponse = $this->actingAs($trainer)
            ->withSession(['tenant_id' => $tenant->id])
            ->get(route('trainer.students.index'));

        $indexResponse->assertOk()
            ->assertSeeText('Aluno Trainer');

        $generateResponse = $this->actingAs($trainer)
            ->withSession(['tenant_id' => $tenant->id])
            ->post(route('trainer.students.workouts.generate', $student->id), []);

        $generateResponse->assertRedirect(route('trainer.students.show', $student->id));

        Queue::assertPushed(GenerateWorkoutJob::class, function (GenerateWorkoutJob $job) use ($student, $tenant): bool {
            return $job->userId === $student->id && $job->tenantId === $tenant->id;
        });

        $this->assertDatabaseHas('workouts', [
            'user_id' => $student->id,
            'tenant_id' => $tenant->id,
            'status' => 'processing',
        ]);

        $this->assertSame(0, $trainer->fresh()->credits_balance);
    }

    public function test_trainer_panel_completes_full_generation_flow_for_joao_case(): void
    {
        Queue::fake();
        Notification::fake();

        [$tenant,, $trainer] = $this->createTenantContext();

        $student = $this->mockCreateUserTotal([
            'name' => 'Joao',
            'email' => 'joao@trainer.test',
            'profile_type' => Role::STUDENT->value,
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

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => $tenant->id,
            'student_user_id' => $student->id,
            'trainee_user_id' => $trainer->id,
            'linked_by_user_id' => $trainer->id,
            'note' => null,
        ]);

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
            'generation_insights' => [
                'summary' => [
                    'weekly_frequency' => 5,
                    'split_labels' => ['Peito e Triceps', 'Costas e Biceps', 'Pernas A', 'Costas e Deltoides Posteriores', 'Pernas B'],
                ],
                'statistics' => [
                    'training_days' => 5,
                    'specific_exercises' => 20,
                    'cardio_blocks' => 5,
                ],
                'references' => [
                    'Historico recente com excesso de empurradas horizontais.',
                    'Restricoes e lesoes indicam sensibilidade anterior no ombro.',
                ],
                'improvements' => [
                    'A semana foi reequilibrada com maior presenca de puxadas verticais e remadas de suporte.',
                    'A selecao priorizou alternativas com menor estresse articular para o ombro.',
                ],
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

        $response = $this->actingAs($trainer)
            ->withSession(['tenant_id' => $tenant->id])
            ->post(route('trainer.students.workouts.generate', $student->id), []);

        $capturedJob = null;
        $generatedWorkout = Workout::query()->where('user_id', $student->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('trainer.students.show', $student->id));
        $response->assertSessionHas('status', 'Geracao de treino com ilustracoes e recomendacoes iniciada. Saldo atual: 0 credito(s).');

        Queue::assertPushed(GenerateWorkoutJob::class, function (GenerateWorkoutJob $job) use ($student, $tenant, $generatedWorkout, $trainer, &$capturedJob): bool {
            $capturedJob = $job;

            return $job->userId === $student->id
                && $job->tenantId === $tenant->id
                && $job->workoutId === $generatedWorkout->id
                && $job->requestedByUserId === $trainer->id;
        });

        $this->assertInstanceOf(GenerateWorkoutJob::class, $capturedJob);
        $this->assertSame('processing', (string) $generatedWorkout->status);
        $this->assertSame(0, $trainer->fresh()->credits_balance);
        $this->assertSame('active', (string) $previousActiveWorkout->fresh()->request_status);

        $capturedJob->handle(
            app(\App\Services\Workouts\WorkoutGenerationService::class),
            app(SystemSettingsRuntimeService::class),
            app(CreditService::class),
        );

        $generatedWorkout = $generatedWorkout->fresh();

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

        Notification::assertSentTo($trainer, WorkoutGenerationFinishedNotification::class);

        $profileResponse = $this->actingAs($trainer)
            ->withSession(['tenant_id' => $tenant->id])
            ->get(route('trainer.students.show', $student->id));

        $profileResponse->assertOk()
            ->assertSeeText('Resumo do ultimo treino')
            ->assertSeeText('Variacao')
            ->assertSeeText('Equilibrio biomecanico')
            ->assertSeeText('Historico recente com excesso de empurradas horizontais.')
            ->assertSeeText('A selecao priorizou alternativas com menor estresse articular para o ombro.');
    }

    public function test_trainer_panel_refunds_credits_and_deletes_workout_when_generation_fails(): void
    {
        Queue::fake();
        Notification::fake();

        [$tenant,, $trainer] = $this->createTenantContext();

        $student = User::factory()->create([
            'name' => 'Aluno Falha',
            'email' => 'falha@trainer.test',
            'profile_type' => Role::STUDENT->value,
        ]);

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => $tenant->id,
            'student_user_id' => $student->id,
            'trainee_user_id' => $trainer->id,
            'linked_by_user_id' => $trainer->id,
            'note' => null,
        ]);

        $this->mock(WorkoutGenerationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generatePayload')
                ->once()
                ->andThrow(new \RuntimeException('Erro estrutural na IA.'));
        });

        $response = $this->actingAs($trainer)
            ->withSession(['tenant_id' => $tenant->id])
            ->post(route('trainer.students.workouts.generate', $student->id), []);

        $capturedJob = null;
        $generatedWorkout = Workout::query()->where('user_id', $student->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('trainer.students.show', $student->id));
        $this->assertSame(0, $trainer->fresh()->credits_balance);

        Queue::assertPushed(GenerateWorkoutJob::class, function (GenerateWorkoutJob $job) use (&$capturedJob, $generatedWorkout): bool {
            $capturedJob = $job;

            return $job->workoutId === $generatedWorkout->id;
        });

        $this->assertInstanceOf(GenerateWorkoutJob::class, $capturedJob);

        $capturedJob->handle(
            app(WorkoutGenerationService::class),
            app(SystemSettingsRuntimeService::class),
            app(CreditService::class),
        );

        $this->assertDatabaseMissing('workouts', [
            'id' => $generatedWorkout->id,
        ]);
        $this->assertSame(5, $trainer->fresh()->credits_balance);
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $trainer->id,
            'tenant_id' => $tenant->id,
            'amount' => 5,
            'type' => 'refund_workout_error',
        ]);

        Notification::assertSentTo($trainer, WorkoutGenerationFinishedNotification::class, function (WorkoutGenerationFinishedNotification $notification, array $channels) use ($generatedWorkout): bool {
            $payload = $notification->toArray((object) ['name' => 'Trainer']);

            return in_array('database', $channels, true)
                && (int) ($payload['workout_id'] ?? 0) === $generatedWorkout->id
                && str_contains((string) ($payload['message'] ?? ''), 'creditos foram devolvidos e o treino foi removido');
        });
    }

    public function test_trainer_panel_blocks_new_generation_for_the_same_student_for_one_week_after_failure(): void
    {
        Queue::fake();

        [$tenant,, $trainer] = $this->createTenantContext();

        $blockedStudent = User::factory()->create([
            'name' => 'Aluno Bloqueado',
            'email' => 'bloqueado@trainer.test',
            'profile_type' => Role::STUDENT->value,
        ]);

        $freeStudent = User::factory()->create([
            'name' => 'Aluno Livre',
            'email' => 'livre@trainer.test',
            'profile_type' => Role::STUDENT->value,
        ]);

        $tenant->users()->attach($blockedStudent->id, ['role' => Role::STUDENT->value]);
        $tenant->users()->attach($freeStudent->id, ['role' => Role::STUDENT->value]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => $tenant->id,
            'student_user_id' => $blockedStudent->id,
            'trainee_user_id' => $trainer->id,
            'linked_by_user_id' => $trainer->id,
            'note' => null,
        ]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => $tenant->id,
            'student_user_id' => $freeStudent->id,
            'trainee_user_id' => $trainer->id,
            'linked_by_user_id' => $trainer->id,
            'note' => null,
        ]);

        $this->mock(WorkoutGenerationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generatePayload')
                ->once()
                ->andThrow(new \RuntimeException('Weekly plan exceeds hinge frequency recovery threshold.'));
        });

        $this->actingAs($trainer)
            ->withSession(['tenant_id' => $tenant->id])
            ->post(route('trainer.students.workouts.generate', $blockedStudent->id), [])
            ->assertRedirect(route('trainer.students.show', $blockedStudent->id));

        $capturedJob = null;
        $generatedWorkout = Workout::query()->where('user_id', $blockedStudent->id)->latest('id')->firstOrFail();

        Queue::assertPushed(GenerateWorkoutJob::class, function (GenerateWorkoutJob $job) use (&$capturedJob, $generatedWorkout): bool {
            $capturedJob = $job;

            return $job->workoutId === $generatedWorkout->id;
        });

        $this->assertInstanceOf(GenerateWorkoutJob::class, $capturedJob);

        $capturedJob->handle(
            app(WorkoutGenerationService::class),
            app(SystemSettingsRuntimeService::class),
            app(CreditService::class),
        );

        $blockedResponse = $this->actingAs($trainer)
            ->withSession(['tenant_id' => $tenant->id])
            ->from(route('trainer.students.show', $blockedStudent->id))
            ->post(route('trainer.students.workouts.generate', $blockedStudent->id), []);

        $blockedResponse->assertRedirect(route('trainer.students.show', $blockedStudent->id));
        $blockedResponse->assertSessionHasErrors(['workout']);
        $this->assertStringContainsString('temporariamente bloqueada', session('errors')->first('workout'));
        $this->assertStringContainsString('7 dias', session('errors')->first('workout'));

        $freeResponse = $this->actingAs($trainer)
            ->withSession(['tenant_id' => $tenant->id])
            ->post(route('trainer.students.workouts.generate', $freeStudent->id), []);

        $freeResponse->assertRedirect(route('trainer.students.show', $freeStudent->id));
    }

    public function test_trainer_panel_consumes_credits_when_reusing_workout_without_ai(): void
    {
        [$tenant,, $trainer] = $this->createTenantContext();

        $student = User::factory()->create([
            'name' => 'Aluno Reuso',
            'email' => 'reuso@trainer.test',
            'profile_type' => Role::STUDENT->value,
        ]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => $tenant->id,
            'student_user_id' => $student->id,
            'trainee_user_id' => $trainer->id,
            'linked_by_user_id' => $trainer->id,
            'note' => null,
        ]);

        $sourceWorkout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => ['weekly_plan' => [['day' => 'Segunda']]],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $response = $this->actingAs($trainer)
            ->withSession(['tenant_id' => $tenant->id])
            ->post(route('trainer.students.workouts.reuse', [$student->id, $sourceWorkout->id]));

        $newWorkout = \App\Models\Workout\Workout::query()->where('user_id', $student->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('trainer.students.workouts.show', [$student->id, $newWorkout->id]));
        $this->assertNotSame($sourceWorkout->id, $newWorkout->id);
        $this->assertSame(2, $trainer->fresh()->credits_balance);
    }

    private function createTenantContext(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Painel',
            'slug' => 'tenant-painel',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@painel.test',
            'profile_type' => Role::TRAINER->value,
        ]);

        $trainer = User::factory()->create([
            'email' => 'trainer@painel.test',
            'profile_type' => Role::TRAINER->value,
            'credits_balance' => 5,
        ]);

        $tenant->users()->attach($admin->id, ['role' => Role::ADMIN->value]);
        $tenant->users()->attach($trainer->id, ['role' => Role::TRAINER->value]);

        DB::table('tenant_trainee')->insert([
            'tenant_id' => $tenant->id,
            'trainee_user_id' => $trainer->id,
            'linked_by_user_id' => $admin->id,
            'note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $admin, $trainer];
    }
}
