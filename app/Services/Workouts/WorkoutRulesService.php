<?php

namespace App\Services\Workouts;

use Carbon\CarbonImmutable;

class WorkoutRulesService
{
    public function generationCredits(): int
    {
        return $this->intConfig('workouts.credits.generate', 5, 1);
    }

    public function reuseCredits(): int
    {
        return $this->intConfig('workouts.credits.reuse', 3, 1);
    }

    public function reactivationCredits(): int
    {
        return $this->intConfig('workouts.credits.reactivate', 2, 1);
    }

    public function activeDays(): int
    {
        return $this->intConfig('workouts.active_days', 60, 1);
    }

    public function activeFromNow(): array
    {
        $activatedAt = CarbonImmutable::now();

        return [
            'request_status' => 'active',
            'activated_at' => $activatedAt,
            'active_until_at' => $activatedAt->addDays($this->activeDays()),
        ];
    }

    private function intConfig(string $key, int $default, int $min): int
    {
        $value = config($key, $default);

        if (! is_numeric($value)) {
            return $default;
        }

        return max($min, (int) $value);
    }
}
