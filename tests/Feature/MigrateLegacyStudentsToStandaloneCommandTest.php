<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantStudentTraineeLink;
use App\Models\User;
use App\Models\Workout\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrateLegacyStudentsToStandaloneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_migrates_legacy_students_to_standalone_links(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Legado',
            'slug' => 'tenant-legado',
            'is_active' => true,
        ]);

        $trainee = User::factory()->create([
            'name' => 'Trainer Legado',
            'email' => 'legado@trainer.test',
            'profile_type' => Role::TRAINER->value,
            'is_active' => true,
        ]);

        $student = User::factory()->create([
            'name' => 'Aluno Legado',
            'email' => 'legado@student.test',
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
        ]);

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => $tenant->id,
            'student_user_id' => $student->id,
            'trainee_user_id' => $trainee->id,
            'linked_by_user_id' => $trainee->id,
            'note' => 'legado',
        ]);

        Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'completed',
            'workout_plan' => ['weekly_plan' => []],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $this->artisan('students:migrate-standalone')
            ->expectsOutputToContain('Migrated: 1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('tenant_user', [
            'user_id' => $student->id,
            'tenant_id' => $tenant->id,
            'role' => Role::STUDENT->value,
        ]);

        $this->assertDatabaseHas('tenant_student_trainee_links', [
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $trainee->id,
        ]);

        $this->assertSame(0, DB::table('tenant_student_trainee_links')
            ->where('student_user_id', $student->id)
            ->whereNotNull('tenant_id')
            ->count());

        $this->assertDatabaseHas('workouts', [
            'user_id' => $student->id,
            'tenant_id' => null,
        ]);
    }
}