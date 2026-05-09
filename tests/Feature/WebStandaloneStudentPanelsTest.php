<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\GenerateWorkoutJob;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantStudentTraineeLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebStandaloneStudentPanelsTest extends TestCase
{
    use RefreshDatabase;

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
            'tenant_id' => null,
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

        Queue::assertPushed(GenerateWorkoutJob::class, function (GenerateWorkoutJob $job) use ($student): bool {
            return $job->userId === $student->id && $job->tenantId === null;
        });

        $this->assertDatabaseHas('workouts', [
            'user_id' => $student->id,
            'tenant_id' => null,
            'status' => 'processing',
        ]);

        $this->assertSame(0, $trainer->fresh()->credits_balance);
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
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $trainer->id,
            'linked_by_user_id' => $trainer->id,
            'note' => null,
        ]);

        $sourceWorkout = \App\Models\Workout\Workout::query()->create([
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

        $newWorkout = \App\Models\Workout\Workout::query()->where('user_id', $student->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('trainer.students.workouts.show', [$student->id, $newWorkout->id]));
        $this->assertNotSame($sourceWorkout->id, $newWorkout->id);
        $this->assertSame(2, $trainer->fresh()->credits_balance);
    }
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
