<?php

namespace App\Services\Workout\Planning;

use App\DTOs\AI\WorkoutGenerationContext;

class VolumeDistributionService
{
    public function build(WorkoutGenerationContext $context, array $splitPlan, array $trainingMemory): array
    {
        $goal = mb_strtolower((string) ($context->profile['goal'] ?? ''));
        $activityLevel = mb_strtolower((string) ($context->profile['activity_level'] ?? 'moderate'));
        $age = (int) ($context->profile['age'] ?? 30);
        $imc = is_numeric($context->profile['imc'] ?? null) ? (float) $context->profile['imc'] : null;

        $baseSets = str_contains($goal, 'hipertrof') || str_contains($goal, 'massa') ? 16 : 12;
        $activityModifier = match ($activityLevel) {
            'sedentary', 'light' => -4,
            'high', 'advanced', 'intense' => 2,
            default => 0,
        };
        $ageModifier = $age >= 45 ? -2 : 0;
        $imcModifier = $imc !== null && $imc >= 35 ? -2 : 0;
        $conservativeModifier = $context->conservativeMode ? -2 : 0;

        $focusSessions = [];

        foreach ($splitPlan['split'] as $day) {
            foreach ($day['allowed_focus_tokens'] as $focusToken) {
                if ($focusToken === 'core') {
                    continue;
                }

                $focusSessions[$focusToken] = ($focusSessions[$focusToken] ?? 0) + 1;
            }
        }

        $distribution = [];

        foreach (['peito', 'costas', 'pernas', 'ombro', 'bracos', 'core'] as $focus) {
            $sessions = max(1, (int) ($focusSessions[$focus] ?? ($focus === 'core' ? 2 : 1)));
            $undertrainedBonus = in_array($focus, $trainingMemory['undertrained_muscles'] ?? [], true) ? 2 : 0;
            $weeklySets = max(8, min(22, $baseSets + $activityModifier + $ageModifier + $imcModifier + $conservativeModifier + $undertrainedBonus));

            if ($focus === 'core') {
                $weeklySets = max(6, min(12, $weeklySets - 4));
            }

            $distribution[$focus] = [
                'weekly_sets' => $weeklySets,
                'sessions' => $sessions,
                'target_intensity' => $focus === 'core' ? 'moderada' : ($context->conservativeMode ? 'moderada' : 'moderada_alta'),
                'sets_per_session' => max(3, (int) ceil($weeklySets / $sessions)),
            ];
        }

        return $distribution;
    }
}
