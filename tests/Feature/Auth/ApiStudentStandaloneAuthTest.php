<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\MedicalData\MedicalData;
use App\Models\PhysicalData\PhysicalData;
use App\Models\Preferences\Preference;
use App\Models\Tenant\TenantStudentTraineeLink;
use App\Models\User;
use App\Services\Tenant\PlatformTenantService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class ApiStudentStandaloneAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_register_via_api_and_is_linked_to_platform_trainer_without_tenant(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Aluno App',
            'email' => 'aluno@app.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms_of_use' => true,
            'privacy_policy' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('registered', true)
            ->assertJsonPath('profile', Role::STUDENT->value)
            ->assertJsonPath('requiresEmailVerification', true)
            ->assertJsonPath('verification_email_sent', true)
            ->assertJsonPath('assigned_trainer.name', PlatformTenantService::PLATFORM_TRAINEE_NAME)
            ->assertJsonPath('policies.accepted', true)
            ->assertJsonMissingPath('token');

        $student = User::query()->where('email', 'aluno@app.test')->firstOrFail();
        $platformTrainee = User::query()->where('name', PlatformTenantService::PLATFORM_TRAINEE_NAME)->firstOrFail();

        $this->assertSame(Role::STUDENT, $student->profileType());
        $this->assertNull($student->email_verified_at);
        $this->assertDatabaseMissing('tenant_user', [
            'user_id' => $student->id,
        ]);
        $this->assertDatabaseHas('tenant_student_trainee_links', [
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $platformTrainee->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'terms_version' => config('legal.terms.version'),
            'privacy_policy_version' => config('legal.privacy_policy.version'),
        ]);

        Notification::assertSentTo($student, VerifyEmail::class, function (VerifyEmail $notification) use ($student): bool {
            return str_contains((string) $notification->toMail($student)->actionUrl, '/api/v1/auth/verify-email/');
        });
    }

    public function test_student_registration_via_api_requires_policy_acceptance(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Aluno App',
            'email' => 'aluno@app.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['terms_of_use', 'privacy_policy']);
    }

    public function test_auth_options_lists_public_student_mobile_endpoints(): void
    {
        $response = $this->getJson('/api/v1/auth/options');

        $response->assertOk()
            ->assertJsonPath('audience', 'student-mobile')
            ->assertJsonPath('public_endpoints.0.endpoint', '/api/v1/auth/register')
            ->assertJsonPath('public_endpoints.1.endpoint', '/api/v1/auth/login')
            ->assertJsonPath('public_endpoints.2.endpoint', '/api/v1/auth/google')
            ->assertJsonPath('public_endpoints.3.endpoint', '/termos-de-uso')
            ->assertJsonPath('public_endpoints.4.endpoint', '/politica-de-privacidade')
            ->assertJsonPath('authenticated_endpoints.0.endpoint', '/api/v1/auth/accept-policies')
            ->assertJsonPath('authenticated_endpoints.1.endpoint', '/api/v1/me');
    }

    public function test_google_login_requires_explicit_policy_acceptance_on_first_access(): void
    {
        config()->set('services.google.client_id', 'google-client-id');
        config()->set('services.google.client_secret', 'google-client-secret');
        config()->set('services.google.redirect', 'http://localhost/auth/google/callback');

        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-api-user-1');
        $socialiteUser->shouldReceive('getEmail')->andReturn('google-api-user@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Google Api User');

        Socialite::shouldReceive('driver->stateless->userFromToken')
            ->once()
            ->andReturn($socialiteUser);

        $response = $this->postJson('/api/v1/auth/google', [
            'access_token' => 'google-access-token',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'policy_acceptance_required')
            ->assertJsonPath('requiresPolicyAcceptance', true)
            ->assertJsonPath('profile', Role::STUDENT->value);

        $this->assertDatabaseHas('users', [
            'email' => 'google-api-user@example.com',
            'profile_type' => Role::STUDENT->value,
            'auth_provider' => 'google',
        ]);
    }

    public function test_google_login_can_accept_policies_inline_and_return_student_token(): void
    {
        config()->set('services.google.client_id', 'google-client-id');
        config()->set('services.google.client_secret', 'google-client-secret');
        config()->set('services.google.redirect', 'http://localhost/auth/google/callback');

        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-api-user-2');
        $socialiteUser->shouldReceive('getEmail')->andReturn('google-inline@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Google Inline');

        Socialite::shouldReceive('driver->stateless->userFromToken')
            ->once()
            ->andReturn($socialiteUser);

        $response = $this->postJson('/api/v1/auth/google', [
            'access_token' => 'google-access-token',
            'terms_of_use' => true,
            'privacy_policy' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('profile', Role::STUDENT->value)
            ->assertJsonPath('policies.accepted', true)
            ->assertJsonPath('user.email', 'google-inline@example.com')
            ->assertJsonStructure(['token']);
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
        ])->assertOk()
            ->assertJsonPath('tenant_id', null)
            ->assertJsonPath('assigned_trainer.id', $platformTrainee->id);

        $this->putJson('/api/v1/me/trainer', [
            'trainee_user_id' => $replacementTrainee->id,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()->assertJsonPath('assigned_trainer.id', $replacementTrainee->id);

        $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()
            ->assertJsonPath('tenant_id', null)
            ->assertJsonPath('assigned_trainer.id', $replacementTrainee->id);

        $this->assertDatabaseHas('tenant_student_trainee_links', [
            'tenant_id' => null,
            'student_user_id' => $student->id,
            'trainee_user_id' => $replacementTrainee->id,
        ]);
    }

    public function test_unverified_student_cannot_login_via_api(): void
    {
        $student = User::factory()->unverified()->create([
            'name' => 'Aluno Nao Verificado',
            'email' => 'aluno-unverified@app.test',
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Voce precisa confirmar seu e-mail antes de fazer login.');
    }

    public function test_me_endpoint_returns_physical_medical_and_preferences_data(): void
    {
        $platformTrainee = User::factory()->create([
            'name' => PlatformTenantService::PLATFORM_TRAINEE_NAME,
            'email' => 'plataforma-profile@trainer.test',
            'profile_type' => Role::TRAINER->value,
            'is_active' => true,
        ]);

        $student = User::factory()->create([
            'name' => 'Aluno Perfil Completo',
            'email' => 'perfil-completo@app.test',
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
        ]);

        PhysicalData::query()->create([
            'user_id' => $student->id,
            'body_fat_percentage' => 12.5,
            'activity_level' => 'moderate',
            'imc' => 24.3,
        ]);

        MedicalData::query()->create([
            'user_id' => $student->id,
            'injuries' => 'joelho',
            'diseases' => 'nenhuma',
            'medications' => 'nenhum',
            'restrictions' => 'agachamento profundo',
        ]);

        Preference::query()->create([
            'user_id' => $student->id,
            'preferred_foods' => ['frango', 'arroz'],
            'disliked_foods' => ['figado'],
            'drinks' => ['agua', 'cafe'],
            'available_hours' => ['06:00', '19:00'],
            'training_frequency' => 4,
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

        $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()
            ->assertJsonPath('physical_data.activity_level', 'moderate')
            ->assertJsonPath('physical_data.body_fat_percentage', '12.50')
            ->assertJsonPath('medical_data.injuries', 'joelho')
            ->assertJsonPath('preferences.training_frequency', '4')
            ->assertJsonPath('preferences.preferred_foods.0', 'frango');
    }

    public function test_me_endpoint_can_upsert_profile_with_mobile_payload(): void
    {
        $platformTrainee = User::factory()->create([
            'name' => PlatformTenantService::PLATFORM_TRAINEE_NAME,
            'email' => 'plataforma-mobile-payload@trainer.test',
            'profile_type' => Role::TRAINER->value,
            'is_active' => true,
        ]);

        $student = User::factory()->create([
            'name' => 'Aluno Mobile',
            'email' => 'aluno-mobile@app.test',
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

        $payload = [
            'name' => 'Contato Plataforma',
            'email' => 'plataforma@academai.com.br',
            'phone' => '11999999999',
            'birth_date' => '1994-06-17',
            'gender' => 'male',
            'height' => '1.70',
            'weight' => '82.00',
            'physical_data' => [
                'body_fat_percentage' => '20.00',
                'activity_level' => 'high',
                'imc' => '28.40',
            ],
            'medical_data' => [
                'injuries' => '',
                'restrictions' => 'Hipertensao | Doenca cardiovascular',
                'medications' => 'Losartana',
            ],
            'preferences' => [
                'workout_days' => '4x por semana',
                'summary' => 'Hipertrofia com foco em pernas',
                'notifications_enabled' => true,
            ],
        ];

        $this->postJson('/api/v1/me', $payload, [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()
            ->assertJsonPath('name', 'Contato Plataforma')
            ->assertJsonPath('email', 'plataforma@academai.com.br')
            ->assertJsonPath('phone', '(11) 99999-9999')
            ->assertJsonPath('goal', 'Hipertrofia com foco em pernas')
            ->assertJsonPath('physical_data.activity_level', 'high')
            ->assertJsonPath('physical_data.imc', '28.37')
            ->assertJsonPath('medical_data.restrictions', 'Hipertensao | Doenca cardiovascular')
            ->assertJsonPath('preferences.training_frequency', '4x por semana')
            ->assertJsonPath('preferences.workout_days', '4x por semana')
            ->assertJsonPath('preferences.focus_areas', 'Hipertrofia com foco em pernas')
            ->assertJsonPath('preferences.summary', 'Hipertrofia com foco em pernas')
            ->assertJsonPath('preferences.notifications_enabled', true);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => 'Contato Plataforma',
            'email' => 'plataforma@academai.com.br',
            'phone' => '(11) 99999-9999',
            'goal' => 'Hipertrofia com foco em pernas',
        ]);

        $this->assertDatabaseHas('physical_data', [
            'user_id' => $student->id,
            'body_fat_percentage' => '20.00',
            'activity_level' => 'high',
            'imc' => '28.37',
        ]);

        $this->assertDatabaseHas('medical_data', [
            'user_id' => $student->id,
            'injuries' => null,
            'medications' => 'Losartana',
            'restrictions' => 'Hipertensao | Doenca cardiovascular',
        ]);

        $this->assertDatabaseHas('preferences', [
            'user_id' => $student->id,
            'training_frequency' => '4x por semana',
            'notifications_enabled' => true,
        ]);

        $this->putJson('/api/v1/me', [
            'preferences' => [
                'workout_days' => '5x por semana',
                'summary' => 'Definicao e condicionamento',
                'notifications_enabled' => false,
            ],
        ], [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()
            ->assertJsonPath('preferences.training_frequency', '5x por semana')
            ->assertJsonPath('preferences.summary', 'Definicao e condicionamento')
            ->assertJsonPath('preferences.notifications_enabled', false);

        $this->assertDatabaseHas('preferences', [
            'user_id' => $student->id,
            'training_frequency' => '5x por semana',
            'notifications_enabled' => false,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'goal' => 'Definicao e condicionamento',
        ]);
    }

    public function test_api_usage_is_blocked_until_required_policies_are_accepted(): void
    {
        $platformTrainee = User::factory()->create([
            'name' => PlatformTenantService::PLATFORM_TRAINEE_NAME,
            'email' => 'plataforma@trainer.test',
            'profile_type' => Role::TRAINER->value,
            'is_active' => true,
        ]);

        $student = User::factory()->create([
            'name' => 'Aluno Sem Aceite',
            'email' => 'aluno-sem-aceite@app.test',
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
            'terms_version' => null,
            'terms_accepted_at' => null,
            'privacy_policy_version' => null,
            'privacy_policy_accepted_at' => null,
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

        $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer ' . $token,
        ])->assertForbidden()->assertJsonPath('code', 'policy_acceptance_required');

        $this->postJson('/api/v1/auth/accept-policies', [
            'terms_of_use' => true,
            'privacy_policy' => true,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()->assertJsonPath('policies.accepted', true);

        $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk();
    }
}
