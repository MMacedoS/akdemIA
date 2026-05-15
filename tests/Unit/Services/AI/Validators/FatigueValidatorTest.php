<?php

namespace Tests\Unit\Services\AI\Validators;

use App\Services\AI\Validators\FatigueValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FatigueValidatorTest extends TestCase
{
    public function test_it_returns_hinge_weekly_error_in_portuguese(): void
    {
        $validator = new FatigueValidator();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('O plano semanal excedeu o limite de recuperacao para exercicios de hinge.');

        $validator->validate([
            'weekly_plan' => [
                ['focus' => 'Posteriores', 'exercises' => [['workoutx_name' => 'romanian-deadlift']]],
                ['focus' => 'Costas', 'exercises' => [['workoutx_name' => 'deadlift']]],
                ['focus' => 'Pernas', 'exercises' => [['workoutx_name' => 'dumbbell-stiff-leg-deadlift']]],
            ],
        ], [
            'planning_payload' => [
                'fatigue_management' => [
                    'max_hinge_sessions_per_week' => 2,
                ],
            ],
        ]);
    }
}
