<?php

namespace Tests\Unit\Services\AI;

use App\Models\User;
use App\Services\AI\ValidationService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ValidationServiceTest extends TestCase
{
    public function test_validate_workout_response_enriches_exercises_with_steps_and_workoutx_name(): void
    {
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
                            'workoutx_name' => 'Barbell Bench Press',
                        ],
                        [
                            'name' => 'Crucifixo inclinado',
                            'category' => 'specific',
                            'sets' => 3,
                            'reps' => '10-12',
                            'rest' => '45s',
                        ],
                        [
                            'name' => 'Flexao inclinada',
                            'category' => 'specific',
                            'sets' => 3,
                            'reps' => '12',
                            'rest' => '45s',
                        ],
                        [
                            'name' => 'Peck deck',
                            'category' => 'specific',
                            'sets' => 3,
                            'reps' => '12',
                            'rest' => '45s',
                        ],
                        [
                            'name' => 'Caminhada leve',
                            'category' => 'cardio',
                            'sets' => 1,
                            'reps' => '15 min',
                            'rest' => '0s',
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
        $this->assertSame('barbell-bench-press', data_get($exercises, '0.workoutx_name'));
        $this->assertSame('crucifixo-inclinado', data_get($exercises, '1.workoutx_name'));
    }

    public function test_validate_workout_response_rejects_plan_with_less_days_than_training_frequency(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->birth_date = null;
        $user->gender = 'female';
        $user->height = 165;
        $user->weight = 60;
        $user->goal = 'hipertrofia';

        $user->shouldReceive('physicalData')->andReturn(new class
        {
            public function first(): object
            {
                return (object) [
                    'imc' => 24.5,
                    'activity_level' => 'moderate',
                ];
            }
        });

        $user->shouldReceive('medicalData')->andReturn(new class
        {
            public function first(): object
            {
                return (object) [
                    'restrictions' => null,
                    'injuries' => null,
                ];
            }
        });

        $user->shouldReceive('preference')->andReturn(new class
        {
            public function first(): object
            {
                return (object) [
                    'training_frequency' => '5x por semana',
                ];
            }
        });

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
        Mockery::close();

        parent::tearDown();
    }
}
