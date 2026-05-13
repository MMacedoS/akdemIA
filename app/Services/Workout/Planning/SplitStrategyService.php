<?php

namespace App\Services\Workout\Planning;

use App\DTOs\AI\WorkoutGenerationContext;

class SplitStrategyService
{
    public function build(WorkoutGenerationContext $context, array $trainingMemory): array
    {
        $frequency = max(1, min(6, $context->expectedTrainingDays ?? $this->resolveFrequency((string) ($context->profile['training_frequency'] ?? '3'))));

        return [
            'weekly_frequency' => $frequency,
            'split' => match ($frequency) {
                1 => $this->fullBodyTemplate(),
                2 => $this->upperLowerTemplate(),
                3 => $this->pushPullLegsTemplate(),
                4 => $this->upperLowerHybridTemplate(),
                5 => $this->fiveDayTemplate($trainingMemory),
                default => $this->sixDayTemplate(),
            },
        ];
    }

    private function fullBodyTemplate(): array
    {
        return [
            $this->dayBlueprint(1, 'Segunda', 'Full Body', ['chest', 'back', 'legs', 'shoulders'], ['horizontal_push', 'horizontal_pull', 'squat', 'hinge']),
        ];
    }

    private function upperLowerTemplate(): array
    {
        return [
            $this->dayBlueprint(1, 'Segunda', 'Upper', ['chest', 'back', 'shoulders', 'arms'], ['horizontal_push', 'horizontal_pull', 'vertical_push', 'vertical_pull']),
            $this->dayBlueprint(2, 'Quinta', 'Lower', ['legs', 'core'], ['squat', 'hinge', 'lunge']),
        ];
    }

    private function pushPullLegsTemplate(): array
    {
        return [
            $this->dayBlueprint(1, 'Segunda', 'Peito e Triceps', ['chest', 'triceps'], ['horizontal_push', 'vertical_push']),
            $this->dayBlueprint(2, 'Quarta', 'Costas e Biceps', ['back', 'biceps'], ['horizontal_pull', 'vertical_pull']),
            $this->dayBlueprint(3, 'Sexta', 'Pernas', ['legs', 'core'], ['squat', 'hinge', 'lunge']),
        ];
    }

    private function upperLowerHybridTemplate(): array
    {
        return [
            $this->dayBlueprint(1, 'Segunda', 'Upper Push', ['chest', 'shoulders', 'triceps'], ['horizontal_push', 'vertical_push']),
            $this->dayBlueprint(2, 'Terca', 'Lower A', ['legs', 'core'], ['squat', 'lunge']),
            $this->dayBlueprint(3, 'Quinta', 'Upper Pull', ['back', 'biceps'], ['horizontal_pull', 'vertical_pull']),
            $this->dayBlueprint(4, 'Sexta', 'Lower B', ['legs', 'core'], ['hinge', 'squat', 'unilateral']),
        ];
    }

    private function fiveDayTemplate(array $trainingMemory): array
    {
        $needsMoreBack = in_array('costas', $trainingMemory['undertrained_muscles'] ?? [], true);

        return [
            $this->dayBlueprint(1, 'Segunda', 'Peito e Triceps', ['chest', 'triceps'], ['horizontal_push', 'vertical_push']),
            $this->dayBlueprint(2, 'Terca', 'Costas e Biceps', ['back', 'biceps'], ['vertical_pull', 'horizontal_pull']),
            $this->dayBlueprint(3, 'Quarta', 'Pernas A', ['legs', 'core'], ['squat', 'lunge']),
            $this->dayBlueprint(4, 'Quinta', $needsMoreBack ? 'Costas e Ombros' : 'Upper Balance', $needsMoreBack ? ['back', 'shoulders'] : ['chest', 'back', 'shoulders'], ['horizontal_pull', 'vertical_push', 'horizontal_push']),
            $this->dayBlueprint(5, 'Sexta', 'Pernas B', ['legs', 'core'], ['hinge', 'squat', 'unilateral']),
        ];
    }

    private function sixDayTemplate(): array
    {
        return [
            $this->dayBlueprint(1, 'Segunda', 'Push A', ['chest', 'triceps', 'shoulders'], ['horizontal_push', 'vertical_push']),
            $this->dayBlueprint(2, 'Terca', 'Pull A', ['back', 'biceps'], ['vertical_pull', 'horizontal_pull']),
            $this->dayBlueprint(3, 'Quarta', 'Legs A', ['legs', 'core'], ['squat', 'lunge']),
            $this->dayBlueprint(4, 'Quinta', 'Push B', ['chest', 'triceps', 'shoulders'], ['horizontal_push', 'vertical_push']),
            $this->dayBlueprint(5, 'Sexta', 'Pull B', ['back', 'biceps'], ['horizontal_pull', 'vertical_pull']),
            $this->dayBlueprint(6, 'Sabado', 'Legs B', ['legs', 'core'], ['hinge', 'squat', 'unilateral']),
        ];
    }

    private function dayBlueprint(int $day, string $label, string $focusLabel, array $focuses, array $patterns): array
    {
        return [
            'day' => $day,
            'label' => $label,
            'focus_label' => $focusLabel,
            'focuses' => $focuses,
            'allowed_focus_tokens' => $this->mapAllowedFocusTokens($focuses),
            'patterns' => $patterns,
        ];
    }

    private function mapAllowedFocusTokens(array $focuses): array
    {
        $mapping = [
            'chest' => 'peito',
            'back' => 'costas',
            'legs' => 'pernas',
            'shoulders' => 'ombro',
            'biceps' => 'bracos',
            'triceps' => 'bracos',
            'arms' => 'bracos',
            'core' => 'core',
        ];

        return array_values(array_unique(array_map(static fn(string $focus): string => $mapping[$focus] ?? $focus, $focuses)));
    }

    private function resolveFrequency(string $value): int
    {
        if (preg_match('/(\d{1,2})/', $value, $matches) !== 1) {
            return 3;
        }

        return (int) $matches[1];
    }
}
