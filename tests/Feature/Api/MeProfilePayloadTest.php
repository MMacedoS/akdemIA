<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workout\Workout;
use App\Services\Tenant\Auth\TenantAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeProfilePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_endpoint_returns_credits_balance_and_current_workout(): void
    {
        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
            'credits_balance' => 7,
        ]);
        $student->acceptRequiredPolicies();

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

        $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()
            ->assertJsonPath('credits_balance', 7)
            ->assertJsonPath('current_workout.id', $currentWorkout->id)
            ->assertJsonPath('current_workout.status', 'done')
            ->assertJsonPath('current_workout.workout_plan.weekly_plan.0.day', 'monday');
    }
}
