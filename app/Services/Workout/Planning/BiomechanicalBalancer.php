<?php

namespace App\Services\Workout\Planning;

class BiomechanicalBalancer
{
    public function balance(array $splitPlan, array $volumeDistribution): array
    {
        $balancedDays = [];

        foreach ($splitPlan['split'] as $day) {
            $patterns = array_values(array_unique(array_merge(
                $day['patterns'],
                $this->requiredPatternsForFocusTokens($day['allowed_focus_tokens'])
            )));

            $balancedDays[] = array_merge($day, [
                'max_same_pattern_per_session' => 2,
                'required_patterns' => array_values(array_unique(array_intersect($patterns, $day['patterns']))),
                'preferred_order' => $this->preferredOrderForPatterns($patterns),
                'biomechanical_targets' => $this->volumeTargetsForDay($day['allowed_focus_tokens'], $volumeDistribution),
            ]);
        }

        return [
            'weekly_frequency' => $splitPlan['weekly_frequency'],
            'split' => $balancedDays,
        ];
    }

    private function requiredPatternsForFocusTokens(array $focusTokens): array
    {
        $required = [];

        foreach ($focusTokens as $token) {
            $required = array_merge($required, match ($token) {
                'peito' => ['horizontal_push'],
                'costas' => ['horizontal_pull', 'vertical_pull'],
                'pernas' => ['squat', 'hinge'],
                'ombro' => ['vertical_push'],
                'core' => ['rotation'],
                default => [],
            });
        }

        return array_values(array_unique($required));
    }

    private function preferredOrderForPatterns(array $patterns): array
    {
        $priority = [
            'squat',
            'hinge',
            'horizontal_push',
            'vertical_push',
            'horizontal_pull',
            'vertical_pull',
            'lunge',
            'rotation',
            'carry',
            'unilateral',
            'bilateral',
        ];

        usort($patterns, static function (string $left, string $right) use ($priority): int {
            return array_search($left, $priority, true) <=> array_search($right, $priority, true);
        });

        return $patterns;
    }

    private function volumeTargetsForDay(array $focusTokens, array $volumeDistribution): array
    {
        $targets = [];

        foreach ($focusTokens as $focusToken) {
            if (! isset($volumeDistribution[$focusToken])) {
                continue;
            }

            $targets[$focusToken] = $volumeDistribution[$focusToken]['sets_per_session'];
        }

        return $targets;
    }
}
