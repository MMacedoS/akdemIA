<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Jobs\GenerateWorkoutJob;
use App\Models\Tenant\TenantStudentTraineeLink;
use App\Models\User;
use App\Models\Workout\Workout;
use App\Services\Tenant\Auth\TenantAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApiStudentStandaloneWorkoutCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_list_trainers_with_pagination_and_search(): void
    {
        $platformTrainer = User::factory()->create([
            'name' => 'Plataforma',
            'email' => 'plataforma@trainer.test',
            'profile_type' => Role::TRAINER->value,
            'is_active' => true,
        ]);

        $matchTrainer = User::factory()->create([
            'name' => 'Joana Trainer',
            'email' => 'joana@trainer.test',
            'profile_type' => Role::TRAINER->value,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Carlos Trainer',
            'email' => 'carlos@trainer.test',
            'profile_type' => Role::TRAINER->value,
            'is_active' => true,
        ]);

        $student = User::factory()->create([
            'name' => 'Aluno Catalogo',
            'email' => 'catalogo@student.test',
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
        ]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $platformTrainer->id,
            'linked_by_user_id' => $platformTrainer->id,
            'note' => null,
        ]);

        $token = app(TenantAuthService::class)->generateStandaloneToken($student);

        $this->getJson('/api/v1/me/trainers?search=jo&per_page=1', [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()
            ->assertJsonPath('filters.search', 'jo')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $matchTrainer->id)
            ->assertJsonPath('data.0.is_current', false);
    }

    public function test_student_can_generate_and_check_workout_without_tenant(): void
    {
        Queue::fake();

        $platformTrainer = User::factory()->create([
            'name' => 'Plataforma',
            'email' => 'plataforma@trainer.test',
            'profile_type' => Role::TRAINER->value,
            'is_active' => true,
        ]);

        $student = User::factory()->create([
            'name' => 'Aluno Treino',
            'email' => 'treino@student.test',
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
            'credits_balance' => 8,
        ]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $platformTrainer->id,
            'linked_by_user_id' => $platformTrainer->id,
            'note' => null,
        ]);

        $token = app(TenantAuthService::class)->generateStandaloneToken($student);

        $generateResponse = $this->postJson('/api/v1/workouts/generate', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $workoutId = (int) $generateResponse->json('id');

        $generateResponse->assertAccepted()
            ->assertJsonPath('status', 'processing');

        Queue::assertPushed(GenerateWorkoutJob::class, function (GenerateWorkoutJob $job) use ($student, $workoutId): bool {
            return $job->userId === $student->id
                && $job->workoutId === $workoutId
                && $job->tenantId === null;
        });

        Workout::query()->whereKey($workoutId)->update([
            'status' => 'done',
            'workout_plan' => ['weekly_plan' => [['day' => 'monday']]],
            'meal_plan' => [['meal' => 'breakfast']],
            'recommendations' => ['sleep'],
            'cardio_plan' => ['walk'],
            'safety_flags' => [],
        ]);

        $this->getJson('/api/v1/workouts/status/' . $workoutId, [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('result.tenant_id', null)
            ->assertJsonPath('result.user_id', $student->id)
            ->assertJsonPath('result.workout_plan.weekly_plan.0.day', 'monday');
    }
}