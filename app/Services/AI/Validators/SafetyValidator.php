<?php

namespace App\Services\AI\Validators;

use Illuminate\Validation\ValidationException;

class SafetyValidator
{
    public function validate(array $data, array $context = []): void
    {
        $safetyFlags = $context['safety_flags'] ?? [];
        $prohibitedPatterns = [
            '/levantamento\s*terra\s*pesado/i',
            '/agachamento\s*profundo\s*com\s*carga/i',
        ];

        if (($safetyFlags['severe_injury'] ?? false) === true) {
            $prohibitedPatterns[] = '/(burpee|box jump|saltos?|sprint|pliometri)/i';
        }

        if (($safetyFlags['high_imc'] ?? false) === true) {
            $prohibitedPatterns[] = '/(corrida intensa|tiro|sprint|hiit pesado)/i';
        }

        if (($safetyFlags['beginner'] ?? false) === true) {
            $prohibitedPatterns[] = '/(muscle up|snatch|clean and jerk|pistol squat)/i';
        }

        foreach ($data['weekly_plan'] ?? [] as $dayPlan) {
            foreach ($dayPlan['exercises'] ?? [] as $exercise) {
                $name = (string) ($exercise['name'] ?? '');

                foreach ($prohibitedPatterns as $pattern) {
                    if (preg_match($pattern, $name) === 1) {
                        throw ValidationException::withMessages([
                            'workout' => 'Prohibited exercise found: ' . $name,
                        ]);
                    }
                }
            }
        }
    }
}
