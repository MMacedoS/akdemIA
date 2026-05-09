<?php

namespace Tests\Feature\Web\V1\Trainer;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantStudentTraineeLink;
use App\Models\User;
use App\Models\Workout\Workout;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkoutActivationRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_trainer_reactivation_consumes_credits_for_inactive_workout(): void
    {
        [$tenant, $admin, $trainer] = $this->createTenantContext();

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
        ]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $trainer->id,
            'linked_by_user_id' => $admin->id,
            'note' => null,
        ]);

        $workout = Workout::query()->create([
            'tenant_id' => null,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'inactive',
            'workout_plan' => ['weekly_plan' => [['day' => 'Segunda']]],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $response = $this->actingAs($trainer)
            ->withSession(['tenant_id' => $tenant->id])
            ->post(route('trainer.students.workouts.activate', [$student->id, $workout->id]));

        $response->assertRedirect(route('trainer.students.workouts.show', [$student->id, $workout->id]));
        $this->assertSame(3, $trainer->fresh()->credits_balance);
        $this->assertDatabaseHas('workouts', [
            'id' => $workout->id,
            'request_status' => 'active',
        ]);
    }

    public function test_expired_workout_is_marked_inactive_when_opened(): void
    {
        [$tenant, $admin, $trainer] = $this->createTenantContext();

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
        ]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $trainer->id,
            'linked_by_user_id' => $admin->id,
            'note' => null,
        ]);

        $workout = Workout::query()->create([
            'tenant_id' => null,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'active',
            'activated_at' => CarbonImmutable::now()->subDays(61),
            'active_until_at' => CarbonImmutable::now()->subDay(),
            'workout_plan' => ['weekly_plan' => [['day' => 'Segunda']]],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $response = $this->actingAs($trainer)
            ->withSession(['tenant_id' => $tenant->id])
            ->get(route('trainer.students.workouts.show', [$student->id, $workout->id]));

        $response->assertOk();
        $this->assertSame('inactive', $workout->fresh()->request_status);
    }

    public function test_trainer_manual_reuse_consumes_three_credits_once(): void
    {
        [$tenant, $admin, $trainer] = $this->createTenantContext();

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
        ]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $trainer->id,
            'linked_by_user_id' => $admin->id,
            'note' => null,
        ]);

        $sourceWorkout = Workout::query()->create([
            'tenant_id' => null,
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

        $response->assertRedirect();
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
            'email' => 'admin-activation@test.local',
            'profile_type' => Role::TRAINER->value,
        ]);

        $trainer = User::factory()->create([
            'email' => 'trainer-activation@test.local',
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
