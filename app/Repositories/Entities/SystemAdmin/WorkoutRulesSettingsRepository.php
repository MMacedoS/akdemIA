<?php

namespace App\Repositories\Entities\SystemAdmin;

use App\Models\SystemSetting;
use App\Repositories\Contracts\SystemAdmin\WorkoutRulesSettingsRepositoryContract;
use Illuminate\Support\Collection;

class WorkoutRulesSettingsRepository implements WorkoutRulesSettingsRepositoryContract
{
    public function values(): Collection
    {
        return SystemSetting::query()
            ->whereIn('key', [
                'workout.generate_cost',
                'workout.reuse_cost',
                'workout.reactivate_cost',
                'workout.active_days',
            ])
            ->pluck('value', 'key');
    }

    public function update(array $payload): void
    {
        $this->upsert('workout.generate_cost', isset($payload['workout_generate_cost']) ? (string) $payload['workout_generate_cost'] : null);
        $this->upsert('workout.reuse_cost', isset($payload['workout_reuse_cost']) ? (string) $payload['workout_reuse_cost'] : null);
        $this->upsert('workout.reactivate_cost', isset($payload['workout_reactivate_cost']) ? (string) $payload['workout_reactivate_cost'] : null);
        $this->upsert('workout.active_days', isset($payload['workout_active_days']) ? (string) $payload['workout_active_days'] : null);
    }

    private function upsert(string $key, ?string $value): void
    {
        $normalized = is_string($value) ? trim($value) : null;

        if ($normalized === '') {
            $normalized = null;
        }

        SystemSetting::query()->updateOrCreate(
            [
                'domain' => 'workout',
                'key' => $key,
            ],
            [
                'value' => $normalized,
                'is_secret' => false,
            ]
        );
    }
}
