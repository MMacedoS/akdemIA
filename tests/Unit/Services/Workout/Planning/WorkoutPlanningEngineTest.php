<?php

namespace Tests\Unit\Services\Workout\Planning;

use App\DTOs\AI\WorkoutExerciseCandidate;
use App\DTOs\AI\WorkoutGenerationContext;
use App\DTOs\AI\WorkoutRetrievalResult;
use App\Models\User;
use App\Services\Workout\Planning\WorkoutPlanningEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutPlanningEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_planning_engine_builds_deterministic_split_selection_and_scores(): void
    {
        $user = User::factory()->create();

        $context = new WorkoutGenerationContext(
            userId: $user->id,
            tenantId: null,
            profile: [
                'age' => 30,
                'activity_level' => 'moderate',
                'training_frequency' => '1x por semana',
                'goal' => 'hipertrofia',
                'imc' => 25.1,
                'restrictions' => 'Nenhuma',
                'injuries' => 'Nenhuma',
            ],
            previousWorkoutPlan: [],
            conservativeMode: false,
            adjustmentRequest: null,
            expectedTrainingDays: 1,
        );

        $retrieval = new WorkoutRetrievalResult(
            candidates: [
                new WorkoutExerciseCandidate('0009', 'Supino reto com barra', 'barbell-bench-press', 'peito', 'chest', 'pectorals', 'barbell'),
                new WorkoutExerciseCandidate('0010', 'Supino inclinado com halteres', 'incline-dumbbell-bench-press', 'peito', 'chest', 'pectorals', 'dumbbell'),
                new WorkoutExerciseCandidate('0011', 'Crucifixo no cabo', 'cable-fly', 'peito', 'chest', 'pectorals', 'cable'),
                new WorkoutExerciseCandidate('0012', 'Peck deck', 'pec-deck-fly', 'peito', 'chest', 'pectorals', 'machine'),
                new WorkoutExerciseCandidate('1160', 'Caminhada inclinada', 'incline-treadmill-walk', 'cardio', 'cardio', 'cardiovascular system', 'treadmill'),
            ],
            mode: 'unit',
            query: 'teste',
            vectorStoreId: null,
            fileId: null,
        );

        $planning = app(WorkoutPlanningEngine::class)->plan($context, $retrieval);

        $this->assertSame(1, $planning['weekly_frequency']);
        $this->assertCount(1, $planning['selected_days']);
        $this->assertCount(5, data_get($planning, 'selected_days.0.selected_exercises', []));
        $this->assertSame('cardio', data_get($planning, 'selected_days.0.selected_exercises.4.category'));
        $this->assertSame('barbell-bench-press', data_get($planning, 'selected_days.0.selected_exercises.0.workoutx_name'));
        $this->assertGreaterThanOrEqual(1, data_get($planning, 'quality_scores.variation_score', 0));
    }
}
