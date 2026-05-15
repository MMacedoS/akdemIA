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

    public function test_repair_engine_rebuilds_full_weekly_plan_when_ai_omits_a_day(): void
    {
        $engine = app(WorkoutRepairEngine::class);

        $context = new WorkoutGenerationContext(
            userId: 1,
            tenantId: null,
            profile: [
                'goal' => 'emagrecimento',
                'activity_level' => 'moderate',
                'training_frequency' => '2x por semana',
            ],
            previousWorkoutPlan: [],
            conservativeMode: false,
            adjustmentRequest: null,
            expectedTrainingDays: 2,
        );

        $retrieval = new WorkoutRetrievalResult(
            candidates: [],
            mode: 'unit',
            query: 'teste',
            vectorStoreId: null,
            fileId: null,
        );

        $planningPayload = [
            'selected_days' => [
                [
                    'label' => 'Segunda',
                    'focus_label' => 'Peito',
                    'selected_exercises' => [
                        ['name' => 'Supino reto com barra', 'category' => 'specific', 'sets' => 3, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Controle.', 'steps' => ['Ajuste a pegada', 'Desca com controle'], 'remote_exercise_id' => '0009', 'workoutx_name' => 'barbell-bench-press'],
                        ['name' => 'Supino inclinado com halteres', 'category' => 'specific', 'sets' => 3, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Estabilidade.', 'steps' => ['Ajuste os halteres', 'Empurre'], 'remote_exercise_id' => '0010', 'workoutx_name' => 'incline-dumbbell-bench-press'],
                        ['name' => 'Crucifixo no cabo', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Sem balanco.', 'steps' => ['Posicione as polias', 'Aproxime as maos'], 'remote_exercise_id' => '0011', 'workoutx_name' => 'cable-crossover'],
                        ['name' => 'Peck deck', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Escapulas firmes.', 'steps' => ['Ajuste o banco', 'Feche os bracos'], 'remote_exercise_id' => '0012', 'workoutx_name' => 'pec-deck'],
                        ['name' => 'Caminhada inclinada', 'category' => 'cardio', 'sets' => 1, 'reps' => '15 min', 'rest' => '0s', 'notes' => 'Moderado.', 'steps' => ['Inicie leve', 'Mantenha ritmo'], 'remote_exercise_id' => '1160', 'workoutx_name' => 'incline-treadmill-walk'],
                    ],
                ],
                [
                    'label' => 'Quarta',
                    'focus_label' => 'Costas',
                    'selected_exercises' => [
                        ['name' => 'Puxada alta aberta', 'category' => 'specific', 'sets' => 3, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Controle.', 'steps' => ['Ajuste a barra', 'Puxe ao peito'], 'remote_exercise_id' => '0020', 'workoutx_name' => 'lat-pulldown'],
                        ['name' => 'Remada sentada', 'category' => 'specific', 'sets' => 3, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Estabilidade.', 'steps' => ['Ajuste o triangulo', 'Puxe sem impulso'], 'remote_exercise_id' => '0021', 'workoutx_name' => 'cable-row'],
                        ['name' => 'Pulldown bracos estendidos', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Sem balanco.', 'steps' => ['Ajuste a polia', 'Desca os bracos com controle'], 'remote_exercise_id' => '0022', 'workoutx_name' => 'straight-arm-pulldown'],
                        ['name' => 'Rosca no cabo', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Escapulas firmes.', 'steps' => ['Ajuste a barra', 'Flexione os cotovelos'], 'remote_exercise_id' => '0023', 'workoutx_name' => 'cable-biceps-curl'],
                        ['name' => 'Bicicleta moderada', 'category' => 'cardio', 'sets' => 1, 'reps' => '15 min', 'rest' => '0s', 'notes' => 'Moderado.', 'steps' => ['Inicie leve', 'Mantenha ritmo'], 'remote_exercise_id' => '1161', 'workoutx_name' => 'stationary-bike'],
                    ],
                ],
            ],
        ];

        $candidatePlan = [
            'weekly_plan' => [[
                'day' => 'Segunda',
                'focus' => 'Peito',
                'exercises' => [
                    ['name' => 'Supino reto com barra', 'category' => 'specific', 'sets' => 3, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Controle.', 'steps' => ['Ajuste a pegada', 'Desca com controle'], 'remote_exercise_id' => '0009', 'workoutx_name' => 'barbell-bench-press'],
                    ['name' => 'Supino inclinado com halteres', 'category' => 'specific', 'sets' => 3, 'reps' => '8-12', 'rest' => '60s', 'notes' => 'Estabilidade.', 'steps' => ['Ajuste os halteres', 'Empurre'], 'remote_exercise_id' => '0010', 'workoutx_name' => 'incline-dumbbell-bench-press'],
                    ['name' => 'Crucifixo no cabo', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Sem balanco.', 'steps' => ['Posicione as polias', 'Aproxime as maos'], 'remote_exercise_id' => '0011', 'workoutx_name' => 'cable-crossover'],
                    ['name' => 'Peck deck', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Escapulas firmes.', 'steps' => ['Ajuste o banco', 'Feche os bracos'], 'remote_exercise_id' => '0012', 'workoutx_name' => 'pec-deck'],
                    ['name' => 'Caminhada inclinada', 'category' => 'cardio', 'sets' => 1, 'reps' => '15 min', 'rest' => '0s', 'notes' => 'Moderado.', 'steps' => ['Inicie leve', 'Mantenha ritmo'], 'remote_exercise_id' => '1160', 'workoutx_name' => 'incline-treadmill-walk'],
                ],
            ]],
        ];

        $repaired = $engine->repair(
            $context,
            $retrieval,
            $planningPayload,
            $candidatePlan,
            new WorkoutValidationException('weekly_plan must contain exactly 2 day(s) according to training_frequency.', [
                'workout' => ['weekly_plan must contain exactly 2 day(s) according to training_frequency.'],
            ]),
        );

        $this->assertCount(2, data_get($repaired, 'weekly_plan', []));
        $this->assertSame('Segunda', data_get($repaired, 'weekly_plan.0.day'));
        $this->assertSame('Quarta', data_get($repaired, 'weekly_plan.1.day'));
        $this->assertCount(5, data_get($repaired, 'weekly_plan.1.exercises', []));
    }
}
