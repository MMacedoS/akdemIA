<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Tenant\TenantStudentTraineeLink;
use App\Models\User;
use App\Services\Tenant\PlatformTenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiStudentStandaloneAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_register_via_api_and_is_linked_to_platform_trainer_without_tenant(): void
    {
        $response = $this->postJson('/api/v1/auth/register/student', [
            'name' => 'Aluno App',
            'email' => 'aluno@app.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('profile', Role::STUDENT->value)
            ->assertJsonPath('assigned_trainer.name', PlatformTenantService::PLATFORM_TRAINEE_NAME);

        $student = User::query()->where('email', 'aluno@app.test')->firstOrFail();
        $platformTrainee = User::query()->where('name', PlatformTenantService::PLATFORM_TRAINEE_NAME)->firstOrFail();

        $this->assertSame(Role::STUDENT, $student->profileType());
        $this->assertDatabaseMissing('tenant_user', [
            'user_id' => $student->id,
        ]);
        $this->assertDatabaseHas('tenant_student_trainee_links', [
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $platformTrainee->id,
        ]);
    }

    public function test_student_can_login_without_tenant_and_change_trainer(): void
    {
        $platformTrainee = User::factory()->create([
            'name' => PlatformTenantService::PLATFORM_TRAINEE_NAME,
            'email' => 'plataforma@trainer.test',
            'profile_type' => Role::TRAINER->value,
            'is_active' => true,
        ]);

        $replacementTrainee = User::factory()->create([
            'name' => 'Treinador Novo',
            'email' => 'novo@trainer.test',
            'profile_type' => Role::TRAINER->value,
            'is_active' => true,
        ]);

        $student = User::factory()->create([
            'name' => 'Aluno App',
            'email' => 'aluno@login.test',
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
        ]);

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $platformTrainee->id,
            'linked_by_user_id' => $platformTrainee->id,
            'note' => null,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'password',
        ]);

        $token = (string) $loginResponse->json('token');

        $loginResponse->assertOk()
            ->assertJsonPath('profile', Role::STUDENT->value)
            ->assertJsonPath('assigned_trainer.id', $platformTrainee->id);

        $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()->assertJsonPath('tenant_id', null);

        $this->putJson('/api/v1/me/trainer', [
            'trainee_user_id' => $replacementTrainee->id,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()->assertJsonPath('assigned_trainer.id', $replacementTrainee->id);

        $this->assertDatabaseHas('tenant_student_trainee_links', [
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $replacementTrainee->id,
        ]);
    }
}
