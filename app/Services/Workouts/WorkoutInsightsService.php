<?php

namespace App\Services\Workouts;

use App\Models\Workout\Workout;
use Illuminate\Support\Collection;

class WorkoutInsightsService
{
    public function summarize(array $workoutPlan): array
    {
        $weeklyPlan = is_array($workoutPlan['weekly_plan'] ?? null)
            ? $workoutPlan['weekly_plan']
            : [];
        $qualityScores = is_array($workoutPlan['quality_scores'] ?? null)
            ? $workoutPlan['quality_scores']
            : [];
        $generationInsights = is_array($workoutPlan['generation_insights'] ?? null)
            ? $workoutPlan['generation_insights']
            : [];

        $statistics = $this->buildStatistics($weeklyPlan, $generationInsights);
        $references = $this->normalizeStringList($generationInsights['references'] ?? null);
        $improvements = $this->normalizeStringList($generationInsights['improvements'] ?? null);
        $splitLabels = $this->normalizeStringList(data_get($generationInsights, 'summary.split_labels'));

        return [
            'quality_scores' => $this->normalizeQualityScores($qualityScores),
            'statistics' => $statistics,
            'references' => $references,
            'improvements' => $improvements,
            'summary' => [
                'weekly_frequency' => (int) ($statistics['training_days'] ?? 0),
                'split_labels' => $splitLabels,
            ],
            'has_content' => $qualityScores !== [] || $references !== [] || $improvements !== [] || $statistics !== [],
        ];
    }

    public function aggregate(Collection $workouts): array
    {
        $normalizedWorkouts = $workouts
            ->filter(static fn(mixed $workout): bool => $workout instanceof Workout)
            ->values();

        if ($normalizedWorkouts->isEmpty()) {
            return [
                'recent_workouts' => 0,
                'average_quality_scores' => [],
                'training_days_total' => 0,
                'specific_exercises_total' => 0,
                'cardio_blocks_total' => 0,
                'references' => [],
                'improvements' => [],
                'has_content' => false,
            ];
        }

        $insights = $normalizedWorkouts
            ->map(fn(Workout $workout): array => $this->summarize(is_array($workout->workout_plan) ? $workout->workout_plan : []));

        $scoreBuckets = [];

        foreach ($insights as $insight) {
            foreach (($insight['quality_scores'] ?? []) as $score) {
                $key = (string) ($score['key'] ?? '');

                if ($key === '') {
                    continue;
                }

                $scoreBuckets[$key]['label'] = (string) ($score['label'] ?? $key);
                $scoreBuckets[$key]['values'][] = (int) ($score['value'] ?? 0);
            }
        }

        $averageQualityScores = [];

        foreach ($scoreBuckets as $key => $bucket) {
            $values = is_array($bucket['values'] ?? null) ? $bucket['values'] : [];

            if ($values === []) {
                continue;
            }

            $averageQualityScores[] = [
                'key' => $key,
                'label' => (string) ($bucket['label'] ?? $key),
                'value' => (int) round(array_sum($values) / count($values)),
            ];
        }

        $references = $insights
            ->flatMap(static fn(array $insight): array => is_array($insight['references'] ?? null) ? $insight['references'] : [])
            ->map(static fn(mixed $reference): string => trim((string) $reference))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $improvements = $insights
            ->flatMap(static fn(array $insight): array => is_array($insight['improvements'] ?? null) ? $insight['improvements'] : [])
            ->map(static fn(mixed $improvement): string => trim((string) $improvement))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'recent_workouts' => $normalizedWorkouts->count(),
            'average_quality_scores' => $averageQualityScores,
            'training_days_total' => (int) $insights->sum(static fn(array $insight): int => (int) data_get($insight, 'statistics.training_days', 0)),
            'specific_exercises_total' => (int) $insights->sum(static fn(array $insight): int => (int) data_get($insight, 'statistics.specific_exercises', 0)),
            'cardio_blocks_total' => (int) $insights->sum(static fn(array $insight): int => (int) data_get($insight, 'statistics.cardio_blocks', 0)),
            'references' => $references,
            'improvements' => $improvements,
            'has_content' => $averageQualityScores !== [] || $references !== [] || $improvements !== [],
        ];
    }

    private function buildStatistics(array $weeklyPlan, array $generationInsights): array
    {
        $trainingDays = count($weeklyPlan);
        $specificExercises = 0;
        $cardioBlocks = 0;

        foreach ($weeklyPlan as $dayPlan) {
            $exercises = is_array($dayPlan['exercises'] ?? null) ? $dayPlan['exercises'] : [];

            foreach ($exercises as $exercise) {
                if (! is_array($exercise)) {
                    continue;
                }

                if (($exercise['category'] ?? 'specific') === 'cardio') {
                    $cardioBlocks++;
                    continue;
                }

                $specificExercises++;
            }
        }

        return [
            'training_days' => (int) data_get($generationInsights, 'statistics.training_days', $trainingDays),
            'specific_exercises' => (int) data_get($generationInsights, 'statistics.specific_exercises', $specificExercises),
            'cardio_blocks' => (int) data_get($generationInsights, 'statistics.cardio_blocks', $cardioBlocks),
        ];
    }

    private function normalizeQualityScores(array $scores): array
    {
        $labels = [
            'variation_score' => 'Variacao',
            'fatigue_score' => 'Fadiga',
            'novelty_score' => 'Novidade',
            'biomechanical_balance' => 'Equilibrio biomecanico',
            'recovery_score' => 'Recuperacao',
        ];

        $normalized = [];

        foreach ($labels as $key => $label) {
            if (! isset($scores[$key]) || ! is_numeric($scores[$key])) {
                continue;
            }

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'value' => (int) round((float) $scores[$key]),
            ];
        }

        return $normalized;
    }

    private function normalizeStringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn(mixed $value): string => trim((string) $value), $values)));
    }
}
