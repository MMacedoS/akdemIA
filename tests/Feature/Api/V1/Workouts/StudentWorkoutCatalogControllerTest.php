<?php

namespace Tests\Feature\Api\V1\Workouts;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workout\ExerciseMediaCache;
use App\Models\Workout\Workout;
use App\Models\Workout\WorkoutCatalog;
use App\Models\Workout\WorkoutCatalogUserLink;
use App\Services\Tenant\Auth\TenantAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentWorkoutCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_list_only_public_catalogs_not_linked_yet(): void
    {
        $student = $this->createStandaloneStudent(credits: 10);
        $owner = $this->createTrainer();

        $publicCatalog = $this->createCatalog($owner, [
            'name' => 'Catalogo Publico A',
            'is_public' => true,
            'price' => 0,
        ]);

        $privateLinkedCatalog = $this->createCatalog($owner, [
            'name' => 'Catalogo Privado Vinculado',
            'is_public' => false,
            'price' => 2,
        ]);

        $this->createCatalog($owner, [
            'name' => 'Catalogo Privado Oculto',
            'is_public' => false,
            'price' => 0,
        ]);

        WorkoutCatalogUserLink::query()->create([
            'user_id' => $student->id,
            'workouts_catalog_id' => $privateLinkedCatalog->id,
            'credits_consumed' => 0,
            'linked_at' => now(),
        ]);

        $exerciseIds = collect(range(1, 5))->map(function (int $index): int {
            return (int) ExerciseMediaCache::query()->create([
                'remote_exercise_id' => 'r-' . $index,
                'localized_name_pt_br' => 'Exercicio ' . $index,
                'workoutx_name' => 'exercise-' . $index,
                'query_name' => 'Exercise ' . $index,
                'remote_gif_url' => null,
                'storage_path' => null,
                'payload' => [],
            ])->id;
        })->all();

        $publicCatalog->exercises()->sync([
            $exerciseIds[0] => ['order' => 1],
            $exerciseIds[1] => ['order' => 2],
            $exerciseIds[2] => ['order' => 3],
            $exerciseIds[3] => ['order' => 4],
            $exerciseIds[4] => ['order' => 5],
        ]);

        $response = $this->getJson('/api/v1/students/catalogs', $this->authHeaders($student))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $rowsById = collect($response->json('data'))->keyBy('id');

        $this->assertTrue($rowsById->has($publicCatalog->id));
        $this->assertFalse($rowsById->has($privateLinkedCatalog->id));
        $this->assertFalse((bool) data_get($rowsById->get($publicCatalog->id), 'is_linked'));
        $this->assertSame(0, (int) data_get($rowsById->get($publicCatalog->id), 'credit_price'));
        $this->assertCount(4, (array) data_get($rowsById->get($publicCatalog->id), 'exercises_preview', []));
        $this->assertSame('Exercicio 1', (string) data_get($rowsById->get($publicCatalog->id), 'exercises_preview.0.name'));
        $this->assertStringContainsString('/api/v1/workouts/exercises/media/exercise-1', (string) data_get($rowsById->get($publicCatalog->id), 'exercises_preview.0.gif_url'));
    }

    public function test_student_can_list_only_linked_catalogs_in_mine_endpoint_with_related_workouts(): void
    {
        $student = $this->createStandaloneStudent(credits: 10);
        $owner = $this->createTrainer();

        $linkedCatalog = $this->createCatalog($owner, [
            'name' => 'Catalogo Vinculado',
            'is_public' => false,
        ]);

        $unlinkedCatalog = $this->createCatalog($owner, [
            'name' => 'Catalogo Sem Vinculo',
            'is_public' => true,
        ]);

        WorkoutCatalogUserLink::query()->create([
            'user_id' => $student->id,
            'workouts_catalog_id' => $linkedCatalog->id,
            'credits_consumed' => 0,
            'linked_at' => now(),
        ]);

        Workout::query()->create([
            'tenant_id' => null,
            'user_id' => $student->id,
            'source_workout_catalog_id' => $linkedCatalog->id,
            'source_workout_catalog_name' => $linkedCatalog->name,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => ['weekly_plan' => []],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        Workout::query()->create([
            'tenant_id' => null,
            'user_id' => $student->id,
            'source_workout_catalog_id' => $unlinkedCatalog->id,
            'source_workout_catalog_name' => $unlinkedCatalog->name,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => ['weekly_plan' => []],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $response = $this->getJson('/api/v1/students/catalogs/mine', $this->authHeaders($student))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $linkedCatalog->id)
            ->assertJsonPath('data.0.is_linked', true)
            ->assertJsonPath('data.0.workouts.0.source_workout_catalog_id', $linkedCatalog->id);

        $rowsById = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($rowsById->has($linkedCatalog->id));
        $this->assertFalse($rowsById->has($unlinkedCatalog->id));
    }

    public function test_student_can_link_public_catalog_and_consume_credits_when_catalog_has_price(): void
    {
        $student = $this->createStandaloneStudent(credits: 10);
        $owner = $this->createTrainer();

        $catalog = $this->createCatalog($owner, [
            'name' => 'Catalogo Pago',
            'is_public' => true,
            'price' => 4,
        ]);

        $this->postJson('/api/v1/students/catalogs/' . $catalog->id . '/link', [], $this->authHeaders($student))
            ->assertCreated()
            ->assertJsonPath('credits_consumed', 4)
            ->assertJsonPath('credits_balance', 6)
            ->assertJsonPath('data.id', $catalog->id)
            ->assertJsonPath('data.is_linked', true)
            ->assertJsonPath('workout.source_workout_catalog_id', $catalog->id)
            ->assertJsonPath('workout.user_id', $student->id)
            ->assertJsonPath('workout.status', 'done');

        $this->assertDatabaseHas('workout_catalog_user_links', [
            'user_id' => $student->id,
            'workouts_catalog_id' => $catalog->id,
            'credits_consumed' => 4,
        ]);

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $student->id,
            'amount' => -4,
            'type' => 'consume_catalog_link',
        ]);
    }

    public function test_student_link_endpoint_is_idempotent_and_does_not_consume_credits_again(): void
    {
        $student = $this->createStandaloneStudent(credits: 10);
        $owner = $this->createTrainer();

        $catalog = $this->createCatalog($owner, [
            'name' => 'Catalogo Publico Ja Vinculado',
            'is_public' => true,
            'price' => 3,
        ]);

        WorkoutCatalogUserLink::query()->create([
            'user_id' => $student->id,
            'workouts_catalog_id' => $catalog->id,
            'credits_consumed' => 0,
            'linked_at' => now(),
        ]);

        $this->postJson('/api/v1/students/catalogs/' . $catalog->id . '/link', [], $this->authHeaders($student))
            ->assertOk()
            ->assertJsonPath('credits_consumed', 0)
            ->assertJsonPath('credits_balance', 10)
            ->assertJsonPath('data.is_linked', true);

        $this->assertSame(10, (int) $student->fresh()->credits_balance);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_student_cannot_link_paid_catalog_without_enough_credits(): void
    {
        $student = $this->createStandaloneStudent(credits: 1);
        $owner = $this->createTrainer();

        $catalog = $this->createCatalog($owner, [
            'name' => 'Catalogo Pago Sem Saldo',
            'is_public' => true,
            'price' => 5,
        ]);

        $this->postJson('/api/v1/students/catalogs/' . $catalog->id . '/link', [], $this->authHeaders($student))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Saldo de credito insuficiente para a operacao solicitada.');

        $this->assertDatabaseMissing('workout_catalog_user_links', [
            'user_id' => $student->id,
            'workouts_catalog_id' => $catalog->id,
        ]);
    }

    public function test_linking_catalog_does_not_inactivate_active_workout_without_catalog(): void
    {
        $student = $this->createStandaloneStudent(credits: 10);
        $owner = $this->createTrainer();

        $catalog = $this->createCatalog($owner, [
            'name' => 'Catalogo Ativacao',
            'is_public' => true,
            'price' => 0,
        ]);

        $exercise = ExerciseMediaCache::query()->create([
            'remote_exercise_id' => 'r-catalog-1',
            'localized_name_pt_br' => 'Exercicio Base',
            'workoutx_name' => 'exercise-base',
            'query_name' => 'Exercise Base',
            'remote_gif_url' => null,
            'storage_path' => null,
            'payload' => [],
        ]);

        $catalog->exercises()->sync([
            (int) $exercise->id => ['order' => 1],
        ]);

        $nonCatalogWorkout = Workout::query()->create([
            'tenant_id' => null,
            'user_id' => $student->id,
            'source_workout_catalog_id' => null,
            'source_workout_catalog_name' => null,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => ['weekly_plan' => []],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $this->postJson('/api/v1/students/catalogs/' . $catalog->id . '/link', [], $this->authHeaders($student))
            ->assertCreated();

        $this->assertSame('active', (string) ($nonCatalogWorkout->fresh()?->request_status ?? ''));
    }

    private function createStandaloneStudent(int $credits): User
    {
        return User::factory()->create([
            'name' => 'Aluno Teste ' . Str::random(5),
            'email' => 'student-' . Str::random(8) . '@example.test',
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
            'credits_balance' => $credits,
            'terms_version' => config('legal.terms.version'),
            'terms_accepted_at' => now(),
            'privacy_policy_version' => config('legal.privacy_policy.version'),
            'privacy_policy_accepted_at' => now(),
        ]);
    }

    private function createTrainer(): User
    {
        return User::factory()->create([
            'name' => 'Treinador ' . Str::random(5),
            'email' => 'trainer-' . Str::random(8) . '@example.test',
            'profile_type' => Role::TRAINER->value,
            'is_active' => true,
            'terms_version' => config('legal.terms.version'),
            'terms_accepted_at' => now(),
            'privacy_policy_version' => config('legal.privacy_policy.version'),
            'privacy_policy_accepted_at' => now(),
        ]);
    }

    private function createCatalog(User $owner, array $overrides = []): WorkoutCatalog
    {
        return WorkoutCatalog::query()->create(array_merge([
            'name' => 'Catalogo ' . Str::random(8),
            'description' => 'Descricao do catalogo para testes.',
            'quantity_exercises' => 0,
            'price' => 0,
            'user_id' => $owner->id,
            'path_image' => null,
            'is_public' => true,
            'status' => true,
        ], $overrides));
    }

    private function authHeaders(User $student): array
    {
        $token = app(TenantAuthService::class)->generateStandaloneToken($student);

        return [
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
