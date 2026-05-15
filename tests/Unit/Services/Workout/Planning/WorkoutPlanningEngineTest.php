<?php

namespace Tests\Unit\Services\Workout\Planning;

use App\DTOs\AI\WorkoutExerciseCandidate;
use App\DTOs\AI\WorkoutGenerationContext;
use App\DTOs\AI\WorkoutRetrievalResult;
use App\Models\User;
use App\Models\Workout\ExerciseMediaCache;
use App\Services\Workout\Planning\WorkoutPlanningEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutPlanningEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<int, WorkoutExerciseCandidate>
     */
    private function makeCandidates(array $definitions): array
    {
        return array_map(static fn(array $definition): WorkoutExerciseCandidate => new WorkoutExerciseCandidate(
            $definition['id'],
            $definition['name'],
            $definition['workoutx_name'],
            $definition['focus'],
            $definition['body_part'],
            $definition['target'],
            $definition['equipment'],
        ), $definitions);
    }

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

    public function test_planning_engine_rebalances_push_pull_and_protects_shoulder_for_five_day_hypertrophy_case(): void
    {
        $user = User::factory()->create();

        foreach ([1, 2] as $weekOffset) {
            \App\Models\Workout\Workout::query()->create([
                'tenant_id' => null,
                'user_id' => $user->id,
                'status' => 'done',
                'request_status' => 'inactive',
                'workout_plan' => [
                    'weekly_plan' => [
                        [
                            'day' => 'Segunda',
                            'focus' => 'Peito',
                            'exercises' => [
                                ['name' => 'Supino reto com barra', 'sets' => 4, 'remote_exercise_id' => 'bench-barbell', 'workoutx_name' => 'barbell-bench-press'],
                                ['name' => 'Supino reto com barra', 'sets' => 4, 'remote_exercise_id' => 'bench-barbell', 'workoutx_name' => 'barbell-bench-press'],
                                ['name' => 'Supino inclinado com halteres', 'sets' => 3, 'remote_exercise_id' => 'bench-incline-dumbbell', 'workoutx_name' => 'incline-dumbbell-bench-press'],
                                ['name' => 'Remada sentada', 'sets' => 3, 'remote_exercise_id' => 'row-seated', 'workoutx_name' => 'cable-row'],
                            ],
                        ],
                        [
                            'day' => 'Quarta',
                            'focus' => 'Peito e Ombro',
                            'exercises' => [
                                ['name' => 'Supino reto com barra', 'sets' => 4, 'remote_exercise_id' => 'bench-barbell', 'workoutx_name' => 'barbell-bench-press'],
                                ['name' => 'Crucifixo no cabo', 'sets' => 3, 'remote_exercise_id' => 'fly-cable', 'workoutx_name' => 'cable-fly'],
                                ['name' => 'Desenvolvimento com barra', 'sets' => 3, 'remote_exercise_id' => 'shoulder-barbell', 'workoutx_name' => 'barbell-shoulder-press'],
                            ],
                        ],
                    ],
                ],
                'meal_plan' => [],
                'recommendations' => [],
                'cardio_plan' => [],
                'safety_flags' => [],
                'created_at' => now()->subWeeks($weekOffset),
                'updated_at' => now()->subWeeks($weekOffset),
            ]);
        }

        $context = new WorkoutGenerationContext(
            userId: $user->id,
            tenantId: null,
            profile: [
                'age' => 31,
                'activity_level' => 'moderate',
                'training_frequency' => '5x por semana',
                'goal' => 'hipertrofia',
                'imc' => 24.8,
                'restrictions' => 'Evitar sobrecarga com empurradas horizontais excessivas.',
                'injuries' => 'Leve desconforto anterior no ombro direito.',
            ],
            previousWorkoutPlan: [
                'weekly_plan' => [
                    [
                        'day' => 'Segunda',
                        'focus' => 'Peito',
                        'exercises' => [
                            ['remote_exercise_id' => 'bench-barbell'],
                        ],
                    ],
                ],
            ],
            conservativeMode: false,
            adjustmentRequest: null,
            expectedTrainingDays: 5,
        );

        $retrieval = new WorkoutRetrievalResult(
            candidates: $this->makeCandidates([
                ['id' => 'bench-barbell', 'name' => 'Supino reto com barra', 'workoutx_name' => 'barbell-bench-press', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'barbell'],
                ['id' => 'bench-machine-convergent', 'name' => 'Supino maquina convergente', 'workoutx_name' => 'converging-machine-chest-press', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'machine'],
                ['id' => 'bench-incline-dumbbell', 'name' => 'Supino inclinado com halteres', 'workoutx_name' => 'incline-dumbbell-bench-press', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'dumbbell'],
                ['id' => 'fly-cable', 'name' => 'Crucifixo no cabo', 'workoutx_name' => 'cable-fly', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'cable'],
                ['id' => 'triceps-rope', 'name' => 'Triceps corda', 'workoutx_name' => 'cable-rope-pushdown', 'focus' => 'triceps', 'body_part' => 'upper arms', 'target' => 'triceps', 'equipment' => 'cable'],
                ['id' => 'triceps-machine', 'name' => 'Triceps maquina', 'workoutx_name' => 'machine-triceps-extension', 'focus' => 'triceps', 'body_part' => 'upper arms', 'target' => 'triceps', 'equipment' => 'machine'],
                ['id' => 'lat-pulldown-wide', 'name' => 'Puxada alta aberta', 'workoutx_name' => 'lat-pulldown', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'lats', 'equipment' => 'cable'],
                ['id' => 'pull-up-assisted', 'name' => 'Barra assistida', 'workoutx_name' => 'assisted-pull-up', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'lats', 'equipment' => 'machine'],
                ['id' => 'cable-row', 'name' => 'Remada sentada', 'workoutx_name' => 'cable-row', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'middle back', 'equipment' => 'cable'],
                ['id' => 'chest-supported-row', 'name' => 'Remada com apoio no peito', 'workoutx_name' => 'chest-supported-row', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'middle back', 'equipment' => 'machine'],
                ['id' => 'straight-arm-pulldown', 'name' => 'Pulldown bracos estendidos', 'workoutx_name' => 'straight-arm-pulldown', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'lats', 'equipment' => 'cable'],
                ['id' => 'rear-delt-fly', 'name' => 'Crucifixo invertido maquina', 'workoutx_name' => 'reverse-pec-deck-fly', 'focus' => 'ombro', 'body_part' => 'shoulders', 'target' => 'rear delts', 'equipment' => 'machine'],
                ['id' => 'lateral-raise-cable', 'name' => 'Elevacao lateral no cabo', 'workoutx_name' => 'cable-lateral-raise', 'focus' => 'ombro', 'body_part' => 'shoulders', 'target' => 'lateral delts', 'equipment' => 'cable'],
                ['id' => 'shoulder-press-machine', 'name' => 'Desenvolvimento maquina', 'workoutx_name' => 'machine-shoulder-press', 'focus' => 'ombro', 'body_part' => 'shoulders', 'target' => 'delts', 'equipment' => 'machine'],
                ['id' => 'barbell-squat', 'name' => 'Agachamento livre', 'workoutx_name' => 'barbell-squat', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'quads', 'equipment' => 'barbell'],
                ['id' => 'leg-press', 'name' => 'Leg press', 'workoutx_name' => 'leg-press', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'quads', 'equipment' => 'machine'],
                ['id' => 'walking-lunge', 'name' => 'Avanco andando', 'workoutx_name' => 'walking-lunge', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'glutes', 'equipment' => 'dumbbell'],
                ['id' => 'romanian-deadlift', 'name' => 'Levantamento romeno', 'workoutx_name' => 'romanian-deadlift', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'hamstrings', 'equipment' => 'barbell'],
                ['id' => 'hip-thrust', 'name' => 'Hip thrust', 'workoutx_name' => 'barbell-hip-thrust', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'glutes', 'equipment' => 'barbell'],
                ['id' => 'leg-curl', 'name' => 'Mesa flexora', 'workoutx_name' => 'lying-leg-curl', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'hamstrings', 'equipment' => 'machine'],
                ['id' => 'plank', 'name' => 'Prancha', 'workoutx_name' => 'plank', 'focus' => 'core', 'body_part' => 'waist', 'target' => 'abs', 'equipment' => 'body weight'],
                ['id' => 'woodchop', 'name' => 'Woodchop no cabo', 'workoutx_name' => 'cable-woodchop', 'focus' => 'core', 'body_part' => 'waist', 'target' => 'obliques', 'equipment' => 'cable'],
                ['id' => 'biceps-curl-dumbbell', 'name' => 'Rosca alternada', 'workoutx_name' => 'dumbbell-biceps-curl', 'focus' => 'biceps', 'body_part' => 'upper arms', 'target' => 'biceps', 'equipment' => 'dumbbell'],
                ['id' => 'biceps-curl-cable', 'name' => 'Rosca no cabo', 'workoutx_name' => 'cable-biceps-curl', 'focus' => 'biceps', 'body_part' => 'upper arms', 'target' => 'biceps', 'equipment' => 'cable'],
                ['id' => 'treadmill-walk', 'name' => 'Caminhada moderada', 'workoutx_name' => 'treadmill-walk', 'focus' => 'cardio', 'body_part' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'treadmill'],
                ['id' => 'bike', 'name' => 'Bicicleta ergometrica', 'workoutx_name' => 'stationary-bike', 'focus' => 'cardio', 'body_part' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'bike'],
            ]),
            mode: 'unit',
            query: 'hipertrofia com ombro sensivel e maior necessidade de puxada vertical',
            vectorStoreId: null,
            fileId: null,
        );

        $planning = app(WorkoutPlanningEngine::class)->plan($context, $retrieval);

        $specificExercises = collect($planning['selected_days'])
            ->flatMap(fn(array $day): array => array_filter($day['selected_exercises'] ?? [], static fn(array $exercise): bool => ($exercise['category'] ?? 'specific') === 'specific'));

        $selectedPatterns = $specificExercises
            ->flatMap(fn(array $exercise): array => $exercise['patterns'] ?? [])
            ->values();
        $horizontalPushCount = $selectedPatterns->filter(static fn(string $pattern): bool => $pattern === 'horizontal_push')->count();
        $verticalPullCount = $selectedPatterns->filter(static fn(string $pattern): bool => $pattern === 'vertical_pull')->count();

        $selectedIds = $specificExercises
            ->pluck('remote_exercise_id')
            ->all();

        $this->assertSame(5, $planning['weekly_frequency']);
        $this->assertTrue(data_get($planning, 'training_memory.imbalance_flags.horizontal_push_excess'));
        $this->assertTrue(data_get($planning, 'training_memory.imbalance_flags.vertical_pull_deficit'));
        $this->assertTrue(data_get($planning, 'training_memory.imbalance_flags.shoulder_sensitive'));
        $this->assertGreaterThan(data_get($planning, 'volume_distribution.peito.weekly_sets'), data_get($planning, 'volume_distribution.costas.weekly_sets'));
        $this->assertContains('bench-machine-convergent', $selectedIds);
        $this->assertNotContains('bench-barbell', $selectedIds);
        $this->assertGreaterThanOrEqual(2, $verticalPullCount);
        $this->assertLessThanOrEqual($verticalPullCount + 2, $horizontalPushCount);
        $this->assertSame('Costas e Deltoides Posteriores', data_get($planning, 'selected_days.3.focus'));
    }

    public function test_planning_engine_avoids_weekly_specific_duplicates_and_keeps_five_exercises_per_day(): void
    {
        $user = User::factory()->create();

        $context = new WorkoutGenerationContext(
            userId: $user->id,
            tenantId: null,
            profile: [
                'age' => 31,
                'activity_level' => 'moderate',
                'training_frequency' => '5x por semana',
                'goal' => 'hipertrofia',
                'imc' => 24.8,
                'restrictions' => 'Evitar excesso de empurradas horizontais pesadas.',
                'injuries' => 'Leve desconforto anterior no ombro direito.',
            ],
            previousWorkoutPlan: [],
            conservativeMode: false,
            adjustmentRequest: null,
            expectedTrainingDays: 5,
        );

        $retrieval = new WorkoutRetrievalResult(
            candidates: $this->makeCandidates([
                ['id' => 'chest-press-machine', 'name' => 'Supino maquina', 'workoutx_name' => 'machine-chest-press', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'machine'],
                ['id' => 'cable-fly', 'name' => 'Crucifixo no cabo', 'workoutx_name' => 'cable-fly', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'cable'],
                ['id' => 'incline-dumbbell-press', 'name' => 'Supino inclinado com halteres', 'workoutx_name' => 'incline-dumbbell-bench-press', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'dumbbell'],
                ['id' => 'pec-deck', 'name' => 'Peck deck', 'workoutx_name' => 'pec-deck-fly', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'machine'],
                ['id' => 'triceps-rope', 'name' => 'Triceps corda', 'workoutx_name' => 'cable-rope-pushdown', 'focus' => 'triceps', 'body_part' => 'upper arms', 'target' => 'triceps', 'equipment' => 'cable'],
                ['id' => 'triceps-machine', 'name' => 'Triceps maquina', 'workoutx_name' => 'machine-triceps-extension', 'focus' => 'triceps', 'body_part' => 'upper arms', 'target' => 'triceps', 'equipment' => 'machine'],
                ['id' => 'triceps-overhead-rope', 'name' => 'Triceps testa na corda', 'workoutx_name' => 'overhead-rope-triceps-extension', 'focus' => 'triceps', 'body_part' => 'upper arms', 'target' => 'triceps', 'equipment' => 'cable'],
                ['id' => 'pull-up-assisted', 'name' => 'Barra assistida', 'workoutx_name' => 'assisted-pull-up', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'lats', 'equipment' => 'machine'],
                ['id' => 'lat-pulldown', 'name' => 'Puxada alta aberta', 'workoutx_name' => 'lat-pulldown', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'lats', 'equipment' => 'cable'],
                ['id' => 'cable-row', 'name' => 'Remada sentada', 'workoutx_name' => 'cable-row', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'middle back', 'equipment' => 'cable'],
                ['id' => 'barbell-row', 'name' => 'Remada curvada', 'workoutx_name' => 'barbell-bent-over-row', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'upper back', 'equipment' => 'barbell'],
                ['id' => 'straight-arm-pulldown', 'name' => 'Pulldown bracos estendidos', 'workoutx_name' => 'straight-arm-pulldown', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'lats', 'equipment' => 'cable'],
                ['id' => 'biceps-cable', 'name' => 'Rosca no cabo', 'workoutx_name' => 'cable-biceps-curl', 'focus' => 'biceps', 'body_part' => 'upper arms', 'target' => 'biceps', 'equipment' => 'cable'],
                ['id' => 'biceps-dumbbell', 'name' => 'Rosca alternada', 'workoutx_name' => 'dumbbell-biceps-curl', 'focus' => 'biceps', 'body_part' => 'upper arms', 'target' => 'biceps', 'equipment' => 'dumbbell'],
                ['id' => 'rear-delt-fly', 'name' => 'Crucifixo invertido maquina', 'workoutx_name' => 'reverse-pec-deck-fly', 'focus' => 'ombro', 'body_part' => 'shoulders', 'target' => 'rear delts', 'equipment' => 'machine'],
                ['id' => 'lateral-raise', 'name' => 'Elevacao lateral', 'workoutx_name' => 'dumbbell-lateral-raise', 'focus' => 'ombro', 'body_part' => 'shoulders', 'target' => 'lateral delts', 'equipment' => 'dumbbell'],
                ['id' => 'face-pull', 'name' => 'Face pull', 'workoutx_name' => 'cable-face-pull', 'focus' => 'ombro', 'body_part' => 'shoulders', 'target' => 'rear delts', 'equipment' => 'cable'],
                ['id' => 'leg-press', 'name' => 'Leg press', 'workoutx_name' => 'leg-press', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'quads', 'equipment' => 'machine'],
                ['id' => 'goblet-squat', 'name' => 'Agachamento goblet', 'workoutx_name' => 'goblet-squat', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'quads', 'equipment' => 'dumbbell'],
                ['id' => 'walking-lunge', 'name' => 'Avanco andando', 'workoutx_name' => 'walking-lunge', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'glutes', 'equipment' => 'dumbbell'],
                ['id' => 'leg-curl', 'name' => 'Mesa flexora', 'workoutx_name' => 'lying-leg-curl', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'hamstrings', 'equipment' => 'machine'],
                ['id' => 'romanian-deadlift', 'name' => 'Levantamento romeno', 'workoutx_name' => 'romanian-deadlift', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'hamstrings', 'equipment' => 'barbell'],
                ['id' => 'hip-thrust', 'name' => 'Hip thrust', 'workoutx_name' => 'barbell-hip-thrust', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'glutes', 'equipment' => 'barbell'],
                ['id' => 'plank', 'name' => 'Prancha', 'workoutx_name' => 'plank', 'focus' => 'core', 'body_part' => 'waist', 'target' => 'abs', 'equipment' => 'body weight'],
                ['id' => 'woodchop', 'name' => 'Woodchop no cabo', 'workoutx_name' => 'cable-woodchop', 'focus' => 'core', 'body_part' => 'waist', 'target' => 'obliques', 'equipment' => 'cable'],
                ['id' => 'hanging-knee-raise', 'name' => 'Elevacao de joelhos suspenso', 'workoutx_name' => 'hanging-knee-raise', 'focus' => 'core', 'body_part' => 'waist', 'target' => 'abs', 'equipment' => 'body weight'],
                ['id' => 'bike', 'name' => 'Bicicleta ergometrica', 'workoutx_name' => 'stationary-bike', 'focus' => 'cardio', 'body_part' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'bike'],
                ['id' => 'walk', 'name' => 'Caminhada moderada', 'workoutx_name' => 'treadmill-walk', 'focus' => 'cardio', 'body_part' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'treadmill'],
                ['id' => 'elliptical', 'name' => 'Eliptico leve', 'workoutx_name' => 'elliptical-trainer', 'focus' => 'cardio', 'body_part' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'elliptical'],
            ]),
            mode: 'unit',
            query: 'split de 5 dias com dois dias de costas e pool limitado',
            vectorStoreId: null,
            fileId: null,
        );

        $planning = app(WorkoutPlanningEngine::class)->plan($context, $retrieval);

        $this->assertCount(5, $planning['selected_days']);
        foreach ($planning['selected_days'] as $selectedDay) {
            $this->assertCount(5, $selectedDay['selected_exercises']);
            $this->assertCount(4, array_filter(
                $selectedDay['selected_exercises'],
                static fn(array $exercise): bool => ($exercise['category'] ?? null) === 'specific'
            ));
        }

        $weeklySpecificIds = collect($planning['selected_days'])
            ->flatMap(fn(array $day): array => array_filter(
                $day['selected_exercises'] ?? [],
                static fn(array $exercise): bool => ($exercise['category'] ?? null) === 'specific'
            ))
            ->pluck('remote_exercise_id');

        $this->assertSame($weeklySpecificIds->count(), $weeklySpecificIds->unique()->count());
    }

    public function test_planning_engine_complements_retrieval_with_catalog_even_when_retrieval_already_has_twenty_four_candidates(): void
    {
        $user = User::factory()->create();

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => 'shoulder-machine-press',
            'localized_name_pt_br' => 'Desenvolvimento maquina',
            'workoutx_name' => 'machine-shoulder-press',
            'query_name' => 'Machine Shoulder Press',
            'payload' => [
                'id' => 'shoulder-machine-press',
                'name' => 'Machine Shoulder Press',
                'bodyPart' => 'shoulders',
                'target' => 'delts',
                'equipment' => 'machine',
            ],
        ]);
        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => 'lateral-raise-cable',
            'localized_name_pt_br' => 'Elevacao lateral no cabo',
            'workoutx_name' => 'cable-lateral-raise',
            'query_name' => 'Cable Lateral Raise',
            'payload' => [
                'id' => 'lateral-raise-cable',
                'name' => 'Cable Lateral Raise',
                'bodyPart' => 'shoulders',
                'target' => 'lateral delts',
                'equipment' => 'cable',
            ],
        ]);
        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => 'reverse-pec-deck',
            'localized_name_pt_br' => 'Crucifixo invertido maquina',
            'workoutx_name' => 'reverse-pec-deck-fly',
            'query_name' => 'Reverse Pec Deck Fly',
            'payload' => [
                'id' => 'reverse-pec-deck',
                'name' => 'Reverse Pec Deck Fly',
                'bodyPart' => 'shoulders',
                'target' => 'rear delts',
                'equipment' => 'machine',
            ],
        ]);

        $context = new WorkoutGenerationContext(
            userId: $user->id,
            tenantId: null,
            profile: [
                'age' => 31,
                'activity_level' => 'moderate',
                'training_frequency' => '5x por semana',
                'goal' => 'emagrecimento',
                'imc' => 28.3,
                'restrictions' => 'nenhuma',
                'injuries' => 'joelho estrala',
            ],
            previousWorkoutPlan: [],
            conservativeMode: false,
            adjustmentRequest: null,
            expectedTrainingDays: 5,
        );

        $retrieval = new WorkoutRetrievalResult(
            candidates: $this->makeCandidates([
                ['id' => '0038', 'name' => 'Drag curl com barra', 'workoutx_name' => 'barbell-drag-curl', 'focus' => 'bracos', 'body_part' => 'upper arms', 'target' => 'biceps', 'equipment' => 'barbell'],
                ['id' => '0011', 'name' => 'Elevacao de joelhos suspenso assistida', 'workoutx_name' => 'assisted-hanging-knee-raise', 'focus' => 'core', 'body_part' => 'waist', 'target' => 'abs', 'equipment' => 'assisted'],
                ['id' => '0015', 'name' => 'Barra fixa assistida com pegada neutra fechada', 'workoutx_name' => 'assisted-parallel-close-grip-pull-up', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'lats', 'equipment' => 'machine'],
                ['id' => '0009', 'name' => 'Paralelas assistidas para peito (ajoelhado)', 'workoutx_name' => 'assisted-chest-dip-kneeling', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'machine'],
                ['id' => '0020', 'name' => 'Prancha de equilibrio', 'workoutx_name' => 'balance-board', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'quads', 'equipment' => 'body weight'],
                ['id' => '2138', 'name' => 'Corrida na bike ergonomica v. 3', 'workoutx_name' => 'stationary-bike-run-v-3', 'focus' => 'cardio', 'body_part' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'bike'],
                ['id' => '0018', 'name' => 'Extensao de triceps em pe assistida', 'workoutx_name' => 'assisted-standing-triceps-extension-with-towel', 'focus' => 'bracos', 'body_part' => 'upper arms', 'target' => 'triceps', 'equipment' => 'assisted'],
                ['id' => '0006', 'name' => 'Toque alternado nos calcanhares', 'workoutx_name' => 'alternate-heel-touchers', 'focus' => 'core', 'body_part' => 'waist', 'target' => 'abs', 'equipment' => 'body weight'],
                ['id' => '0007', 'name' => 'Puxada alta alternada', 'workoutx_name' => 'alternate-lateral-pulldown', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'lats', 'equipment' => 'cable'],
                ['id' => '0025', 'name' => 'Supino com barra', 'workoutx_name' => 'barbell-bench-press', 'focus' => 'peito', 'body_part' => 'chest', 'target' => 'pectorals', 'equipment' => 'barbell'],
                ['id' => '0016', 'name' => 'Flexao de joelhos deitado assistida', 'workoutx_name' => 'assisted-prone-hamstring', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'hamstrings', 'equipment' => 'assisted'],
                ['id' => '0684', 'name' => 'Corrida no equipamento', 'workoutx_name' => 'run-equipment', 'focus' => 'cardio', 'body_part' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'body weight'],
                ['id' => '0023', 'name' => 'Rosca alternada com barra', 'workoutx_name' => 'barbell-alternate-biceps-curl', 'focus' => 'bracos', 'body_part' => 'upper arms', 'target' => 'biceps', 'equipment' => 'barbell'],
                ['id' => '0012', 'name' => 'Elevacao de pernas deitado assistida com puxada lateral', 'workoutx_name' => 'assisted-lying-leg-raise-with-lateral-throw-down', 'focus' => 'core', 'body_part' => 'waist', 'target' => 'abs', 'equipment' => 'assisted'],
                ['id' => '0017', 'name' => 'Barra fixa assistida', 'workoutx_name' => 'assisted-pull-up', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'lats', 'equipment' => 'machine'],
                ['id' => '0028', 'name' => 'Clean and press com barra', 'workoutx_name' => 'barbell-clean-and-press', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'quads', 'equipment' => 'barbell'],
                ['id' => '0798', 'name' => 'Caminhada na bike ergonomica', 'workoutx_name' => 'stationary-bike-walk', 'focus' => 'cardio', 'body_part' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'machine'],
                ['id' => '0019', 'name' => 'Mergulho de triceps ajoelhado assistido', 'workoutx_name' => 'assisted-triceps-dip-kneeling', 'focus' => 'bracos', 'body_part' => 'upper arms', 'target' => 'triceps', 'equipment' => 'machine'],
                ['id' => '0002', 'name' => 'Flexao lateral 45', 'workoutx_name' => '45-0-side-bend', 'focus' => 'core', 'body_part' => 'waist', 'target' => 'abs', 'equipment' => 'body weight'],
                ['id' => '0027', 'name' => 'Remada curvada com barra', 'workoutx_name' => 'barbell-bent-over-row', 'focus' => 'costas', 'body_part' => 'back', 'target' => 'upper back', 'equipment' => 'barbell'],
                ['id' => '0024', 'name' => 'Agachamento frontal no banco com barra', 'workoutx_name' => 'barbell-bench-front-squat', 'focus' => 'pernas', 'body_part' => 'upper legs', 'target' => 'quads', 'equipment' => 'barbell'],
                ['id' => '0630', 'name' => 'Mountain climber', 'workoutx_name' => 'mountain-climber', 'focus' => 'cardio', 'body_part' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'body weight'],
                ['id' => '0501', 'name' => 'Burpee jack', 'workoutx_name' => 'jack-burpee', 'focus' => 'cardio', 'body_part' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'body weight'],
                ['id' => '1201', 'name' => 'Burpee com halteres', 'workoutx_name' => 'dumbbell-burpee', 'focus' => 'cardio', 'body_part' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'dumbbell'],
            ]),
            mode: 'unit',
            query: 'local fallback com 24 candidatos e sem ombro suficiente no retrieval',
            vectorStoreId: null,
            fileId: null,
        );

        $planning = app(WorkoutPlanningEngine::class)->plan($context, $retrieval);

        $this->assertCount(5, data_get($planning, 'selected_days.3.selected_exercises', []));
        $this->assertCount(4, array_filter(
            data_get($planning, 'selected_days.3.selected_exercises', []),
            static fn(array $exercise): bool => ($exercise['category'] ?? null) === 'specific'
        ));
        $this->assertContains(
            'shoulder-machine-press',
            collect(data_get($planning, 'selected_days.3.selected_exercises', []))->pluck('remote_exercise_id')->all()
        );
    }
}
