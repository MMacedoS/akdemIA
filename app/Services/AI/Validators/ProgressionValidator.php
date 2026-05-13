<?php

namespace App\Services\AI\Validators;

use Illuminate\Validation\ValidationException;

class ProgressionValidator
{
    public function validate(array $data, array $context = []): void
    {
        $planningPayload = $context['planning_payload'] ?? [];
        $volumeDistribution = $planningPayload['volume_distribution'] ?? [];
        $plannedDays = collect($planningPayload['selected_days'] ?? [])->keyBy('label');

        foreach (($data['weekly_plan'] ?? []) as $index => $dayPlan) {
            $plannedDay = $plannedDays->get((string) ($dayPlan['day'] ?? '')) ?? $plannedDays->values()->get($index);

            if (! is_array($plannedDay)) {
                continue;
            }

            $expectedVolume = 0;

            foreach (($plannedDay['allowed_focus_tokens'] ?? []) as $focusToken) {
                $expectedVolume += (int) ($volumeDistribution[$focusToken]['sets_per_session'] ?? 0);
            }

            if ($expectedVolume === 0) {
                continue;
            }

            $currentVolume = collect($dayPlan['exercises'] ?? [])
                ->where('category', 'specific')
                ->sum(fn(array $exercise): int => (int) ($exercise['sets'] ?? 0));

            if ($currentVolume < max(8, $expectedVolume - 6)) {
                throw ValidationException::withMessages([
                    'workout' => 'Session volume fell below deterministic progression target for day: ' . ($dayPlan['focus'] ?? 'Treino'),
                ]);
            }
        }
    }
}
