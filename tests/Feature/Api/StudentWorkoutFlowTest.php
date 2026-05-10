<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Jobs\GenerateWorkoutJob;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use App\Services\Tenant\Auth\TenantAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StudentWorkoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_list_current_workout_and_history_from_api(): void
    {
        $tenant = Tenant::factory()->create();

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

        $token = app(TenantAuthService::class)->generateToken($student, $tenant);

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

        $tenant = Tenant::factory()->create();

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
            'credits_balance' => 8,
        ]);

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        $token = app(TenantAuthService::class)->generateToken($student, $tenant);

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

        $this->assertSame(3, $student->fresh()->credits_balance);
    }
}
