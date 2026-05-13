<?php

namespace Tests\Unit\Services\AI;

use App\Models\Workout\ExerciseMediaCache;
use App\Services\AI\ValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_workout_response_enriches_exercises_with_steps_and_workoutx_name(): void
    {
        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0025',
            'workoutx_name' => 'barbell-bench-press',
            'query_name' => 'Barbell Bench Press',
            'payload' => [
                'id' => '0025',
                'name' => 'Barbell Bench Press',
                'bodyPart' => 'chest',
            ],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0201',
            'workoutx_name' => 'crucifixo-inclinado',
            'query_name' => 'Crucifixo Inclinado',
            'payload' => [
                'id' => '0201',
                'name' => 'Incline Fly',
                'bodyPart' => 'chest',
            ],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0202',
            'workoutx_name' => 'flexao-inclinada',
            'query_name' => 'Flexao Inclinada',
            'payload' => [
                'id' => '0202',
                'name' => 'Incline Push Up',
                'bodyPart' => 'chest',
            ],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0203',
            'workoutx_name' => 'peck-deck',
            'query_name' => 'Peck Deck',
            'payload' => [
                'id' => '0203',
                'name' => 'Pec Deck Fly',
                'bodyPart' => 'chest',
            ],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '1160',
            'workoutx_name' => 'caminhada-leve',
            'query_name' => 'Caminhada Leve',
            'payload' => [
                'id' => '1160',
                'name' => 'Light Walk',
                'bodyPart' => 'cardio',
            ],
        ]);

        $service = new ValidationService();

        $result = $service->validateWorkoutResponse([
            'weekly_plan' => [
                [
                    'day' => 'Segunda',
                    'focus' => 'Peito',
                    'exercises' => [
                        [
                            'name' => 'Supino reto',
                            'category' => 'specific',
                            'sets' => 4,
                            'reps' => '8-12',
                            'rest' => '60s',
                            'notes' => 'Mantenha os ombros estabilizados.',
                            'steps' => ['Deite no banco', 'Desca a barra com controle', 'Empurre ate estender os bracos'],
                            'remote_exercise_id' => '0025',
                            'workoutx_name' => 'Barbell Bench Press',
                        ],
                        [
                            'name' => 'Crucifixo inclinado',
                            'category' => 'specific',
                            'sets' => 3,
                            'reps' => '10-12',
                            'rest' => '45s',
                            'remote_exercise_id' => '0201',
                        ],
                        [
                            'name' => 'Flexao inclinada',
                            'category' => 'specific',
                            'sets' => 3,
                            'reps' => '12',
                            'rest' => '45s',
                            'remote_exercise_id' => '0202',
                        ],
                        [
                            'name' => 'Peck deck',
                            'category' => 'specific',
                            'sets' => 3,
                            'reps' => '12',
                            'rest' => '45s',
                            'remote_exercise_id' => '0203',
                        ],
                        [
                            'name' => 'Caminhada leve',
                            'category' => 'cardio',
                            'sets' => 1,
                            'reps' => '15 min',
                            'rest' => '0s',
                            'remote_exercise_id' => '1160',
                        ],
                    ],
                ],
            ],
        ]);

        $exercises = data_get($result, 'weekly_plan.0.exercises', []);

        $this->assertCount(5, $exercises);

        foreach ($exercises as $exercise) {
            $this->assertIsArray(data_get($exercise, 'steps'));
            $this->assertNotEmpty(data_get($exercise, 'steps'));
            $this->assertIsString(data_get($exercise, 'workoutx_name'));
            $this->assertNotEmpty(data_get($exercise, 'workoutx_name'));
        }

        $this->assertSame([
            'Deite no banco',
            'Desca a barra com controle',
            'Empurre ate estender os bracos',
        ], data_get($exercises, '0.steps'));
        $this->assertSame('0025', data_get($exercises, '0.remote_exercise_id'));
        $this->assertSame('barbell-bench-press', data_get($exercises, '0.workoutx_name'));
        $this->assertSame('crucifixo-inclinado', data_get($exercises, '1.workoutx_name'));
    }

    public function test_validate_workout_response_requires_remote_id(): void
    {
        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0025',
            'workoutx_name' => 'barbell-bench-press',
            'payload' => ['id' => '0025', 'name' => 'Barbell Bench Press'],
        ]);

        $service = new ValidationService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Each exercise must contain remote_exercise_id.');

        $service->validateWorkoutResponse([
            'weekly_plan' => [[
                'day' => 'Segunda',
                'focus' => 'Peito',
                'exercises' => [
                    ['name' => 'Supino reto', 'category' => 'specific', 'sets' => 4, 'reps' => '8-12', 'rest' => '60s', 'workoutx_name' => 'Barbell Bench Press'],
                    ['name' => 'Crucifixo reto', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s', 'workoutx_name' => 'Incline Fly'],
                    ['name' => 'Flexao', 'category' => 'specific', 'sets' => 3, 'reps' => '12', 'rest' => '45s', 'workoutx_name' => 'Push Up'],
                    ['name' => 'Peck deck', 'category' => 'specific', 'sets' => 3, 'reps' => '12', 'rest' => '45s', 'workoutx_name' => 'Pec Deck'],
                    ['name' => 'Caminhada leve', 'category' => 'cardio', 'sets' => 1, 'reps' => '15 min', 'rest' => '0s', 'workoutx_name' => 'Walk'],
                ],
            ]],
        ]);
    }

    public function test_validate_workout_response_accepts_remote_id_not_present_in_local_catalog(): void
    {
        $service = new ValidationService();

        $result = $service->validateWorkoutResponse([
            'weekly_plan' => [[
                'day' => 'Segunda',
                'focus' => 'Pernas',
                'exercises' => [
                    ['name' => 'Avanco com halteres', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '60s', 'notes' => 'Controle a passada.', 'steps' => ['Posicione os pes', 'Desca com controle', 'Retorne empurrando o solo'], 'remote_exercise_id' => '9001', 'workoutx_name' => 'dumbbell-lunge'],
                    ['name' => 'Leg press', 'category' => 'specific', 'sets' => 4, 'reps' => '10-12', 'rest' => '60s', 'notes' => 'Nao trave os joelhos.', 'steps' => ['Ajuste o assento', 'Empurre a plataforma', 'Retorne sem perder o controle'], 'remote_exercise_id' => '9002', 'workoutx_name' => 'leg-press'],
                    ['name' => 'Mesa flexora', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s', 'notes' => 'Mantenha o quadril apoiado.', 'steps' => ['Ajuste o rolo', 'Flexione os joelhos', 'Retorne devagar'], 'remote_exercise_id' => '9003', 'workoutx_name' => 'lying-leg-curl'],
                    ['name' => 'Extensora', 'category' => 'specific', 'sets' => 3, 'reps' => '12', 'rest' => '45s', 'notes' => 'Suba sem impulso.', 'steps' => ['Ajuste o encosto', 'Estenda os joelhos', 'Retorne controlando'], 'remote_exercise_id' => '9004', 'workoutx_name' => 'leg-extension'],
                    ['name' => 'Caminhada inclinada', 'category' => 'cardio', 'sets' => 1, 'reps' => '15 min', 'rest' => '0s', 'notes' => 'Ritmo moderado.', 'steps' => ['Inicie leve', 'Ajuste a inclinacao', 'Mantenha a respiracao ritmada'], 'remote_exercise_id' => '9005', 'workoutx_name' => 'incline-treadmill-walk'],
                ],
            ]],
        ]);

        $this->assertSame('9001', data_get($result, 'weekly_plan.0.exercises.0.remote_exercise_id'));
        $this->assertSame('dumbbell-lunge', data_get($result, 'weekly_plan.0.exercises.0.workoutx_name'));
    }

    public function test_validate_workout_response_recovers_remote_id_from_workoutx_name_when_model_sends_slug(): void
    {
        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0456',
            'workoutx_name' => 'dumbbell-lunge',
            'query_name' => 'Dumbbell Lunge',
            'payload' => ['id' => '0456', 'name' => 'Dumbbell Lunge', 'bodyPart' => 'upper legs'],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0457',
            'workoutx_name' => 'leg-press',
            'query_name' => 'Leg Press',
            'payload' => ['id' => '0457', 'name' => 'Leg Press', 'bodyPart' => 'upper legs'],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0458',
            'workoutx_name' => 'romanian-deadlift',
            'query_name' => 'Romanian Deadlift',
            'payload' => ['id' => '0458', 'name' => 'Romanian Deadlift', 'bodyPart' => 'upper legs'],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0459',
            'workoutx_name' => 'leg-extension',
            'query_name' => 'Leg Extension',
            'payload' => ['id' => '0459', 'name' => 'Leg Extension', 'bodyPart' => 'upper legs'],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '1160',
            'workoutx_name' => 'incline-treadmill-walk',
            'query_name' => 'Incline Treadmill Walk',
            'payload' => ['id' => '1160', 'name' => 'Incline Treadmill Walk', 'bodyPart' => 'cardio'],
        ]);

        $service = new ValidationService();

        $result = $service->validateWorkoutResponse([
            'weekly_plan' => [[
                'day' => 'Segunda',
                'focus' => 'Pernas',
                'exercises' => [
                    ['name' => 'Avanco com halteres', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '60s', 'remote_exercise_id' => 'dumbbell-lunge'],
                    ['name' => 'Leg press', 'category' => 'specific', 'sets' => 4, 'reps' => '10-12', 'rest' => '60s', 'remote_exercise_id' => '0457'],
                    ['name' => 'Levantamento romeno', 'category' => 'specific', 'sets' => 3, 'reps' => '8-10', 'rest' => '60s', 'remote_exercise_id' => '0458'],
                    ['name' => 'Extensora', 'category' => 'specific', 'sets' => 3, 'reps' => '12', 'rest' => '45s', 'remote_exercise_id' => '0459'],
                    ['name' => 'Caminhada inclinada', 'category' => 'cardio', 'sets' => 1, 'reps' => '15 min', 'rest' => '0s', 'remote_exercise_id' => '1160'],
                ],
            ]],
        ]);

        $this->assertSame('0456', data_get($result, 'weekly_plan.0.exercises.0.remote_exercise_id'));
        $this->assertSame('dumbbell-lunge', data_get($result, 'weekly_plan.0.exercises.0.workoutx_name'));
    }

    public function test_validate_workout_response_rejects_plan_with_less_days_than_training_frequency(): void
    {
        $user = $this->mockCreateUserTotal();

        $service = new ValidationService();
        $service->validateUserForWorkout($user);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('weekly_plan must contain exactly 5 day(s) according to training_frequency.');

        $service->validateWorkoutResponse([
            'weekly_plan' => [
                [
                    'day' => 'Segunda',
                    'focus' => 'Peito',
                    'exercises' => [
                        ['name' => 'Supino reto', 'category' => 'specific', 'sets' => 4, 'reps' => '8-12', 'rest' => '60s'],
                        ['name' => 'Crucifixo reto', 'category' => 'specific', 'sets' => 3, 'reps' => '10-12', 'rest' => '45s'],
                        ['name' => 'Flexao', 'category' => 'specific', 'sets' => 3, 'reps' => '12', 'rest' => '45s'],
                        ['name' => 'Peck deck', 'category' => 'specific', 'sets' => 3, 'reps' => '12', 'rest' => '45s'],
                        ['name' => 'Caminhada leve', 'category' => 'cardio', 'sets' => 1, 'reps' => '15 min', 'rest' => '0s'],
                    ],
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
