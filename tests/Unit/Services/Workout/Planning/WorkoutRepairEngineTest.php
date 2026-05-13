<?php

namespace Tests\Unit\Services\Workout\Planning;

use App\DTOs\AI\WorkoutGenerationContext;
use App\DTOs\AI\WorkoutRetrievalResult;
use App\Exceptions\AI\WorkoutValidationException;
use App\Services\Workout\Planning\WorkoutRepairEngine;
use Tests\TestCase;

class WorkoutRepairEngineTest extends TestCase
{
    public function test_repair_engine_restores_missing_reps_and_rest_from_defaults(): void
    {
        $engine = app(WorkoutRepairEngine::class);

        $context = new WorkoutGenerationContext(
            userId: 1,
            tenantId: null,
            profile: [
                'goal' => 'hipertrofia',
                'activity_level' => 'moderate',
                'training_frequency' => '1x por semana',
            ],
            previousWorkoutPlan: [],
            conservativeMode: false,
            adjustmentRequest: null,
            expectedTrainingDays: 1,
        );

        $retrieval = new WorkoutRetrievalResult(
            candidates: [],
            mode: 'unit',
            query: 'teste',
            vectorStoreId: null,
            fileId: null,
        );

        $planningPayload = [
            'selected_days' => [[
                'label' => 'Segunda',
                'focus_label' => 'Full Body',
                'selected_exercises' => [
                    ['name' => 'Supino reto com barra', 'category' => 'specific', 'sets' => 5, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Controle.', 'steps' => ['Ajuste a pegada', 'Desca com controle'], 'remote_exercise_id' => '0009', 'workoutx_name' => 'barbell-bench-press'],
                    ['name' => 'Supino inclinado com halteres', 'category' => 'specific', 'sets' => 5, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Estabilidade.', 'steps' => ['Ajuste os halteres', 'Empurre'], 'remote_exercise_id' => '0010', 'workoutx_name' => 'incline-dumbbell-bench-press'],
                    ['name' => 'Crucifixo no cabo', 'category' => 'specific', 'sets' => 5, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Sem balanco.', 'steps' => ['Posicione as polias', 'Aproxime as maos'], 'remote_exercise_id' => '0011', 'workoutx_name' => 'cable-crossover'],
                    ['name' => 'Peck deck', 'category' => 'specific', 'sets' => 5, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Escapulas firmes.', 'steps' => ['Ajuste o banco', 'Feche os bracos'], 'remote_exercise_id' => '0012', 'workoutx_name' => 'pec-deck'],
                    ['name' => 'Caminhada inclinada', 'category' => 'cardio', 'sets' => 1, 'reps' => '15 min', 'rest' => '0s', 'notes' => 'Moderado.', 'steps' => ['Inicie leve', 'Mantenha ritmo'], 'remote_exercise_id' => '1160', 'workoutx_name' => 'incline-treadmill-walk'],
                ],
            ]],
        ];

        $candidatePlan = [
            'weekly_plan' => [[
                'day' => 'Segunda',
                'focus' => 'Peito',
                'exercises' => [
                    ['name' => 'Supino reto com barra', 'category' => 'specific', 'sets' => 5, 'reps' => '', 'rest' => '', 'notes' => 'Controle.', 'steps' => ['Ajuste a pegada', 'Desca com controle'], 'remote_exercise_id' => '0009', 'workoutx_name' => 'barbell-bench-press'],
                    ['name' => 'Supino inclinado com halteres', 'category' => 'specific', 'sets' => 5, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Estabilidade.', 'steps' => ['Ajuste os halteres', 'Empurre'], 'remote_exercise_id' => '0010', 'workoutx_name' => 'incline-dumbbell-bench-press'],
                    ['name' => 'Crucifixo no cabo', 'category' => 'specific', 'sets' => 5, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Sem balanco.', 'steps' => ['Posicione as polias', 'Aproxime as maos'], 'remote_exercise_id' => '0011', 'workoutx_name' => 'cable-crossover'],
                    ['name' => 'Peck deck', 'category' => 'specific', 'sets' => 5, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Escapulas firmes.', 'steps' => ['Ajuste o banco', 'Feche os bracos'], 'remote_exercise_id' => '0012', 'workoutx_name' => 'pec-deck'],
                    ['name' => 'Caminhada inclinada', 'category' => 'cardio', 'sets' => 1, 'reps' => '', 'rest' => '', 'notes' => 'Moderado.', 'steps' => ['Inicie leve', 'Mantenha ritmo'], 'remote_exercise_id' => '1160', 'workoutx_name' => 'incline-treadmill-walk'],
                ],
            ]],
        ];

        $repaired = $engine->repair(
            $context,
            $retrieval,
            $planningPayload,
            $candidatePlan,
            new WorkoutValidationException('Invalid reps format for exercise: Supino reto com barra', [
                'workout' => ['Invalid reps format for exercise: Supino reto com barra'],
            ]),
        );

        $this->assertSame('Full Body', data_get($repaired, 'weekly_plan.0.focus'));
        $this->assertSame('10-12', data_get($repaired, 'weekly_plan.0.exercises.0.reps'));
        $this->assertSame('60s', data_get($repaired, 'weekly_plan.0.exercises.0.rest'));
        $this->assertSame('10-12', data_get($repaired, 'weekly_plan.0.exercises.4.reps'));
        $this->assertSame('0s', data_get($repaired, 'weekly_plan.0.exercises.4.rest'));
    }
}
