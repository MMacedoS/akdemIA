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
            'workout_plan' => [
                'weekly_plan' => [['day' => 'monday']],
                'quality_scores' => [
                    'variation_score' => 88,
                    'fatigue_score' => 84,
                    'novelty_score' => 91,
                    'biomechanical_balance' => 89,
                    'recovery_score' => 86,
                ],
                'generation_insights' => [
                    'summary' => [
                        'weekly_frequency' => 5,
                        'split_labels' => ['Peito e Triceps', 'Costas e Biceps'],
                    ],
                    'statistics' => [
                        'training_days' => 5,
                        'specific_exercises' => 20,
                        'cardio_blocks' => 5,
                    ],
                    'references' => [
                        'Historico recente com excesso de empurradas horizontais.',
                    ],
                    'improvements' => [
                        'A semana foi reequilibrada com maior presenca de puxadas verticais e remadas de suporte.',
                    ],
                ],
            ],
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
            ->assertJsonPath('current_workout.workout_plan.weekly_plan.0.day', 'monday')
            ->assertJsonPath('workout_statistics.recent_workouts', 1)
            ->assertJsonPath('workout_statistics.training_days_total', 5)
            ->assertJsonPath('current_workout.insights.statistics.training_days', 5)
            ->assertJsonPath('current_workout.insights.quality_scores.0.label', 'Variacao')
            ->assertJsonPath('current_workout.insights.references.0', 'Historico recente com excesso de empurradas horizontais.')
            ->assertJsonPath('current_workout.insights.improvements.0', 'A semana foi reequilibrada com maior presenca de puxadas verticais e remadas de suporte.');
    }
}
