<?php

namespace App\Services\Workout\Planning;

use App\DTOs\AI\WorkoutGenerationContext;

class FatigueManagementService
{
    public function build(WorkoutGenerationContext $context, array $splitPlan, array $trainingMemory): array
    {
        $beginner = in_array((string) ($context->profile['activity_level'] ?? ''), ['sedentary', 'light'], true);
        $highImc = is_numeric($context->profile['imc'] ?? null) && (float) $context->profile['imc'] >= 35;
        $fatigueMap = $trainingMemory['fatigue_map'] ?? [];

        return [
            'max_heavy_compounds_per_session' => $beginner || $highImc || $context->conservativeMode ? 2 : 3,
            'max_axial_load_sessions_per_week' => $highImc ? 1 : 2,
            'max_hinge_sessions_per_week' => ($fatigueMap['hinge'] ?? 0) >= 3 ? 1 : 2,
            'cardio_position' => 'last',
            'recovery_bias' => $context->conservativeMode ? 'high' : 'moderate',
            'session_overrides' => array_map(function (array $day): array {
                return [
                    'day' => $day['day'],
                    'label' => $day['label'],
                    'max_same_pattern_per_session' => $day['max_same_pattern_per_session'] ?? 2,
                ];
            }, $splitPlan['split']),
        ];
    }
}
