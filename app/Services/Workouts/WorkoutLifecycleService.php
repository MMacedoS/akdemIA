<?php

namespace App\Services\Workouts;

use App\Models\Workout\Workout;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class WorkoutLifecycleService
{
    public function __construct(
        private readonly WorkoutRulesService $workoutRulesService,
    ) {}

    public function expireExpiredWorkouts(?int $tenantId, int $userId): int
    {
        $now = CarbonImmutable::now();

        $query = Workout::query()
            ->where('user_id', $userId)
            ->where('request_status', 'active')
            ->whereNotNull('active_until_at')
            ->where('active_until_at', '<=', $now);

        if ($tenantId === null) {
            $query->whereNull('tenant_id');
        } else {
            $query->where('tenant_id', $tenantId);
        }

        return $query->update([
            'request_status' => 'inactive',
            'updated_at' => $now,
        ]);
    }

    public function syncWorkoutStatus(Workout $workout): Workout
    {
        if ((string) $workout->request_status !== 'active') {
            return $workout;
        }

        if ($workout->active_until_at !== null && $workout->active_until_at->isPast()) {
            $workout->forceFill([
                'request_status' => 'inactive',
            ])->save();
        }

        return $workout->fresh() ?? $workout;
    }

    public function activateWorkout(Builder $userWorkoutScope, Workout $workout): Workout
    {
        $userWorkoutScope
            ->where('id', '!=', $workout->id)
            ->where('request_status', 'active')
            ->update(['request_status' => 'inactive']);

        $workout->forceFill($this->workoutRulesService->activeFromNow())->save();

        return $workout->fresh() ?? $workout;
    }

    public function inactivateWorkout(Workout $workout): Workout
    {
        $workout->forceFill([
            'request_status' => 'inactive',
        ])->save();

        return $workout->fresh() ?? $workout;
    }

    public function activeAttributes(): array
    {
        return $this->workoutRulesService->activeFromNow();
    }
}
