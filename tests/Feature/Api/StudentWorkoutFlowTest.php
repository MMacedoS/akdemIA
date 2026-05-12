<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Jobs\GenerateWorkoutJob;
use App\Models\Tenant\Plan;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSubscription;
use App\Models\User;
use App\Models\Workout\Workout;
use App\Services\Tenant\Auth\TenantAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

    public function test_student_can_list_current_workout_and_history_from_api(): void
    {
        $tenant = $this->createTenant();

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
        ]);

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        $oldWorkout = Workout::query()->create([
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

        $currentWorkout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => ['weekly_plan' => [['day' => 'monday']]],
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
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $currentWorkout->id)
            ->assertJsonPath('data.1.id', $oldWorkout->id);
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

    public function test_standalone_student_can_list_current_workout_and_history_from_student_api(): void
    {
        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
        ]);

        $oldWorkout = Workout::query()->create([
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
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $currentWorkout->id)
            ->assertJsonPath('data.1.id', $oldWorkout->id);

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
