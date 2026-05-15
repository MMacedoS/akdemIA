<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDashboardInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_dashboard_shows_aggregated_workout_statistics_and_references(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Dashboard',
            'slug' => 'tenant-dashboard',
            'is_active' => true,
        ]);

        $student = $this->mockCreateUserTotal([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
            'goal' => 'hipertrofia',
        ]);

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'request_status' => 'active',
            'workout_plan' => [
                'weekly_plan' => [['day' => 'Segunda']],
                'quality_scores' => [
                    'variation_score' => 88,
                    'fatigue_score' => 84,
                    'novelty_score' => 91,
                    'biomechanical_balance' => 89,
                    'recovery_score' => 86,
                ],
                'generation_insights' => [
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
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        $response = $this->actingAs($student)
            ->withSession(['tenant_id' => $tenant->id])
            ->get(route('students.dashboard'));

        $response->assertOk()
            ->assertSeeText('Tendencia dos ultimos treinos')
            ->assertSeeText('Dias planejados')
            ->assertSeeText('Exercicios especificos')
            ->assertSeeText('Variacao')
            ->assertSeeText('Historico recente com excesso de empurradas horizontais.')
            ->assertSeeText('A semana foi reequilibrada com maior presenca de puxadas verticais e remadas de suporte.');
    }
}
