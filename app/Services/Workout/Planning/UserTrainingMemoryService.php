<?php

namespace App\Services\Workout\Planning;

use App\DTOs\AI\WorkoutGenerationContext;
use App\Models\Workout\Workout;
use Illuminate\Support\Carbon;

class UserTrainingMemoryService
{
    public function build(WorkoutGenerationContext $context): array
    {
        $injuryRestrictions = mb_strtolower(trim(
            (string) ($context->profile['injuries'] ?? '') . ' ' . (string) ($context->profile['restrictions'] ?? '')
        ));
        $recentWorkouts = Workout::query()
            ->where('user_id', $context->userId)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->orderByDesc('created_at')
            ->get(['workout_plan']);

        $recentPatterns = [];
        $movementCounts = [];
        $movementPatternCounts = [];
        $focusCounts = [];
        $fatigueMap = [];
        $weeklyVolume = [];

        foreach ($recentWorkouts as $workout) {
            $weeklyPlan = is_array($workout->workout_plan['weekly_plan'] ?? null)
                ? $workout->workout_plan['weekly_plan']
                : [];

            foreach ($weeklyPlan as $day) {
                $focus = $this->normalizeFocusToken((string) ($day['focus'] ?? ''));

                if ($focus !== null) {
                    $focusCounts[$focus] = ($focusCounts[$focus] ?? 0) + 1;
                }

                foreach (($day['exercises'] ?? []) as $exercise) {
                    $remoteExerciseId = trim((string) ($exercise['remote_exercise_id'] ?? ''));
                    $workoutxName = trim((string) ($exercise['workoutx_name'] ?? ''));
                    $pattern = $this->inferMovementPattern($workoutxName, $focus);
                    $sets = max(0, (int) ($exercise['sets'] ?? 0));

                    if ($pattern !== null) {
                        $recentPatterns[$pattern] = true;
                        $movementPatternCounts[$pattern] = ($movementPatternCounts[$pattern] ?? 0) + 1;
                        $fatigueMap[$pattern] = ($fatigueMap[$pattern] ?? 0) + ($this->isHeavyPattern($pattern) ? 2 : 1);
                    }

                    if ($remoteExerciseId !== '') {
                        $movementCounts[$remoteExerciseId] = ($movementCounts[$remoteExerciseId] ?? 0) + 1;
                    }

                    if ($focus !== null && $sets > 0) {
                        $weeklyVolume[$focus] = ($weeklyVolume[$focus] ?? 0) + $sets;
                    }
                }
            }
        }

        $undertrainedMuscles = [];

        foreach (['peito', 'costas', 'pernas', 'ombro', 'bracos', 'core'] as $focus) {
            if (($focusCounts[$focus] ?? 0) === 0) {
                $undertrainedMuscles[] = $focus;
            }
        }

        $horizontalPushCount = (int) ($movementPatternCounts['horizontal_push'] ?? 0);
        $verticalPullCount = (int) ($movementPatternCounts['vertical_pull'] ?? 0);
        $horizontalPullCount = (int) ($movementPatternCounts['horizontal_pull'] ?? 0);
        $shoulderSensitive = $this->containsShoulderSensitivity($injuryRestrictions);
        $chestOverloaded = (($weeklyVolume['peito'] ?? 0) >= 16) || $horizontalPushCount >= 4;
        $verticalPullDeficit = $verticalPullCount === 0 || ($horizontalPushCount >= max(2, $verticalPullCount + 2));

        return [
            'recent_patterns' => array_values(array_keys($recentPatterns)),
            'overused_movements' => array_values(array_keys(array_filter($movementCounts, static fn(int $count): bool => $count >= 2))),
            'undertrained_muscles' => $undertrainedMuscles,
            'fatigue_map' => $fatigueMap,
            'weekly_volume' => $weeklyVolume,
            'movement_pattern_counts' => $movementPatternCounts,
            'imbalance_flags' => [
                'horizontal_push_excess' => $horizontalPushCount > $verticalPullCount,
                'vertical_pull_deficit' => $verticalPullDeficit,
                'horizontal_pull_deficit' => $horizontalPullCount < $horizontalPushCount,
                'chest_overloaded' => $chestOverloaded,
                'shoulder_sensitive' => $shoulderSensitive,
            ],
        ];
    }

    private function containsShoulderSensitivity(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        foreach (['ombro', 'shoulder', 'manguito', 'rotator cuff', 'clavicula', 'labrum'] as $token) {
            if (str_contains($value, $token)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeFocusToken(string $value): ?string
    {
        $normalized = mb_strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        return match (true) {
            str_contains($normalized, 'chest'), str_contains($normalized, 'peito') => 'peito',
            str_contains($normalized, 'back'), str_contains($normalized, 'costa') => 'costas',
            str_contains($normalized, 'shoulder'), str_contains($normalized, 'ombro') => 'ombro',
            str_contains($normalized, 'leg'), str_contains($normalized, 'glute'), str_contains($normalized, 'quadr'), str_contains($normalized, 'hamstring'), str_contains($normalized, 'perna') => 'pernas',
            str_contains($normalized, 'core'), str_contains($normalized, 'abd'), str_contains($normalized, 'waist') => 'core',
            str_contains($normalized, 'biceps'), str_contains($normalized, 'triceps'), str_contains($normalized, 'arm'), str_contains($normalized, 'bra') => 'bracos',
            default => null,
        };
    }

    private function inferMovementPattern(string $workoutxName, ?string $focus): ?string
    {
        $normalized = mb_strtolower($workoutxName);

        return match (true) {
            str_contains($normalized, 'deadlift'), str_contains($normalized, 'good-morning'), str_contains($normalized, 'hip-thrust') => 'hinge',
            str_contains($normalized, 'lunge'), str_contains($normalized, 'split-squat'), str_contains($normalized, 'step-up') => 'lunge',
            str_contains($normalized, 'squat'), str_contains($normalized, 'leg-press') => 'squat',
            str_contains($normalized, 'pull-up'), str_contains($normalized, 'lat-pulldown') => 'vertical_pull',
            str_contains($normalized, 'row') => 'horizontal_pull',
            str_contains($normalized, 'shoulder-press'), str_contains($normalized, 'overhead-press'), str_contains($normalized, 'arnold-press') => 'vertical_push',
            str_contains($normalized, 'bench'), str_contains($normalized, 'push-up'), str_contains($normalized, 'fly') || str_contains($normalized, 'chest-press') => 'horizontal_push',
            str_contains($normalized, 'woodchop'), str_contains($normalized, 'rotation') => 'rotation',
            str_contains($normalized, 'carry') => 'carry',
            $focus === 'peito' => 'horizontal_push',
            $focus === 'costas' => 'horizontal_pull',
            $focus === 'pernas' => 'squat',
            $focus === 'ombro' => 'vertical_push',
            default => null,
        };
    }

    private function isHeavyPattern(string $pattern): bool
    {
        return in_array($pattern, ['hinge', 'squat', 'horizontal_push', 'horizontal_pull', 'vertical_push', 'vertical_pull'], true);
    }
}
