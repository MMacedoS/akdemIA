<?php

namespace App\Services\Workout\Planning;

class WorkoutQualityScoringService
{
    public function score(array $selectedDays, array $trainingMemory, array $fatiguePlan): array
    {
        $patternCounts = [];
        $equipment = [];
        $novelExercises = 0;
        $totalExercises = 0;
        $heavyCompounds = 0;

        foreach ($selectedDays as $day) {
            foreach ($day['selected_exercises'] as $exercise) {
                foreach (($exercise['patterns'] ?? []) as $pattern) {
                    $patternCounts[$pattern] = ($patternCounts[$pattern] ?? 0) + 1;

                    if (in_array($pattern, ['hinge', 'squat', 'horizontal_push', 'horizontal_pull', 'vertical_push', 'vertical_pull'], true)) {
                        $heavyCompounds++;
                    }
                }

                $equipment[(string) ($exercise['equipment'] ?? 'body weight')] = true;
                $totalExercises++;

                if (! in_array((string) ($exercise['remote_exercise_id'] ?? ''), $trainingMemory['overused_movements'] ?? [], true)) {
                    $novelExercises++;
                }
            }
        }

        $uniquePatterns = count($patternCounts);
        $uniqueEquipment = count($equipment);
        $maxHeavy = max(1, (int) (($fatiguePlan['max_heavy_compounds_per_session'] ?? 2) * max(1, count($selectedDays))));
        $noveltyRatio = $totalExercises > 0 ? $novelExercises / $totalExercises : 0;

        return [
            'variation_score' => min(100, 45 + ($uniquePatterns * 8) + ($uniqueEquipment * 4)),
            'fatigue_score' => max(40, min(100, 100 - max(0, ($heavyCompounds - $maxHeavy) * 8))),
            'novelty_score' => (int) round(50 + ($noveltyRatio * 50)),
            'biomechanical_balance' => min(100, 50 + ($uniquePatterns * 7)),
            'progression_score' => min(100, 55 + (count($selectedDays) * 6)),
            'recovery_score' => max(45, min(100, 95 - max(0, $heavyCompounds - $maxHeavy) * 10)),
        ];
    }
}
