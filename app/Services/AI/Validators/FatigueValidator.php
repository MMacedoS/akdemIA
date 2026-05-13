<?php

namespace App\Services\AI\Validators;

use Illuminate\Validation\ValidationException;

class FatigueValidator
{
    public function validate(array $data, array $context = []): void
    {
        $planningPayload = $context['planning_payload'] ?? [];
        $fatiguePlan = $planningPayload['fatigue_management'] ?? [];
        $maxHeavy = (int) ($fatiguePlan['max_heavy_compounds_per_session'] ?? 3);
        $hingeSessions = 0;

        foreach (($data['weekly_plan'] ?? []) as $dayPlan) {
            $heavyCompounds = 0;
            $hingesInSession = 0;

            foreach (($dayPlan['exercises'] ?? []) as $exercise) {
                $patterns = $this->inferPatterns((string) ($exercise['workoutx_name'] ?? $exercise['name'] ?? ''));

                foreach ($patterns as $pattern) {
                    if (in_array($pattern, ['hinge', 'squat', 'horizontal_push', 'horizontal_pull', 'vertical_push', 'vertical_pull'], true)) {
                        $heavyCompounds++;
                    }

                    if ($pattern === 'hinge') {
                        $hingesInSession++;
                    }
                }
            }

            if ($heavyCompounds > $maxHeavy) {
                throw ValidationException::withMessages([
                    'workout' => 'Session exceeds heavy compound fatigue threshold for day: ' . ($dayPlan['focus'] ?? 'Treino'),
                ]);
            }

            if ($hingesInSession > 1) {
                throw ValidationException::withMessages([
                    'workout' => 'Session exceeds hinge fatigue threshold for day: ' . ($dayPlan['focus'] ?? 'Treino'),
                ]);
            }

            if ($hingesInSession > 0) {
                $hingeSessions++;
            }
        }

        if ($hingeSessions > (int) ($fatiguePlan['max_hinge_sessions_per_week'] ?? 2)) {
            throw ValidationException::withMessages([
                'workout' => 'Weekly plan exceeds hinge frequency recovery threshold.',
            ]);
        }
    }

    private function inferPatterns(string $value): array
    {
        $normalized = mb_strtolower($value);
        $patterns = [];

        if (str_contains($normalized, 'deadlift') || str_contains($normalized, 'romanian')) {
            $patterns[] = 'hinge';
        }

        if (str_contains($normalized, 'squat') || str_contains($normalized, 'leg-press')) {
            $patterns[] = 'squat';
        }

        if (str_contains($normalized, 'bench') || str_contains($normalized, 'fly') || str_contains($normalized, 'chest-press')) {
            $patterns[] = 'horizontal_push';
        }

        if (str_contains($normalized, 'pull-up') || str_contains($normalized, 'pulldown')) {
            $patterns[] = 'vertical_pull';
        }

        if (str_contains($normalized, 'row')) {
            $patterns[] = 'horizontal_pull';
        }

        if (str_contains($normalized, 'shoulder-press') || str_contains($normalized, 'overhead')) {
            $patterns[] = 'vertical_push';
        }

        return $patterns;
    }
}
