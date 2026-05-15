<?php

namespace App\Transformers\Workout;

class WorkoutInsightsTransformer
{
    public function transformSummary(array $insights): array
    {
        return [
            'quality_scores' => $this->normalizeQualityScores($insights['quality_scores'] ?? null),
            'statistics' => $this->normalizeSummaryStatistics($insights['statistics'] ?? null),
            'references' => $this->normalizeStrings($insights['references'] ?? null),
            'improvements' => $this->normalizeStrings($insights['improvements'] ?? null),
            'summary' => $this->normalizeSummaryBlock($insights['summary'] ?? null),
            'has_content' => (bool) ($insights['has_content'] ?? false),
        ];
    }

    public function transformAggregate(array $statistics): array
    {
        return [
            'recent_workouts' => (int) ($statistics['recent_workouts'] ?? 0),
            'average_quality_scores' => $this->normalizeQualityScores($statistics['average_quality_scores'] ?? null),
            'training_days_total' => (int) ($statistics['training_days_total'] ?? 0),
            'specific_exercises_total' => (int) ($statistics['specific_exercises_total'] ?? 0),
            'cardio_blocks_total' => (int) ($statistics['cardio_blocks_total'] ?? 0),
            'references' => $this->normalizeStrings($statistics['references'] ?? null),
            'improvements' => $this->normalizeStrings($statistics['improvements'] ?? null),
            'has_content' => (bool) ($statistics['has_content'] ?? false),
        ];
    }

    private function normalizeQualityScores(mixed $scores): array
    {
        if (! is_array($scores)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $score): ?array {
            if (! is_array($score)) {
                return null;
            }

            $key = trim((string) ($score['key'] ?? ''));

            if ($key === '') {
                return null;
            }

            return [
                'key' => $key,
                'label' => trim((string) ($score['label'] ?? $key)),
                'value' => (int) ($score['value'] ?? 0),
            ];
        }, $scores)));
    }

    private function normalizeSummaryStatistics(mixed $statistics): array
    {
        return [
            'training_days' => (int) data_get($statistics, 'training_days', 0),
            'specific_exercises' => (int) data_get($statistics, 'specific_exercises', 0),
            'cardio_blocks' => (int) data_get($statistics, 'cardio_blocks', 0),
        ];
    }

    private function normalizeSummaryBlock(mixed $summary): array
    {
        return [
            'weekly_frequency' => (int) data_get($summary, 'weekly_frequency', 0),
            'split_labels' => $this->normalizeStrings(data_get($summary, 'split_labels')),
        ];
    }

    private function normalizeStrings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn(mixed $value): string => trim((string) $value), $values)));
    }
}
