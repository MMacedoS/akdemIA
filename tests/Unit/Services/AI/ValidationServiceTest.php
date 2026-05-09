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

    public function test_validate_workout_response_requires_remote_id_when_local_catalog_exists(): void
    {
        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0025',
            'workoutx_name' => 'barbell-bench-press',
            'payload' => ['id' => '0025', 'name' => 'Barbell Bench Press'],
        ]);

        $service = new ValidationService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Each exercise must contain remote_exercise_id from the local catalog.');

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
