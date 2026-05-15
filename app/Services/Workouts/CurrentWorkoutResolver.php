<?php

namespace App\Services\Workouts;

use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use Illuminate\Support\Collection;

class CurrentWorkoutResolver
{
    public function __construct(
        private readonly WorkoutLifecycleService $workoutLifecycleService,
        private readonly WorkoutMediaService $workoutMediaService,
    ) {}

    public function resolveForUser(User $user, mixed $tenant): ?Workout
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : null;

        $this->workoutLifecycleService->expireExpiredWorkouts($tenantId, (int) $user->id);

        $doneWorkout = $this->workoutScope($tenantId, (int) $user->id)
            ->where('status', 'done')
            ->orderByDesc('id')
            ->first();

        if ($doneWorkout instanceof Workout) {
            return $this->hydrateWorkoutMedia($doneWorkout);
        }

        $workout = $this->workoutScope($tenantId, (int) $user->id)
            ->orderByDesc('id')
            ->first();

        return $workout instanceof Workout ? $this->hydrateWorkoutMedia($workout) : null;
    }

    public function recentDoneWorkoutsForUser(User $user, mixed $tenant, int $limit = 3): Collection
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : null;

        $this->workoutLifecycleService->expireExpiredWorkouts($tenantId, (int) $user->id);

        return $this->workoutScope($tenantId, (int) $user->id)
            ->where('status', 'done')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn(Workout $workout): Workout => $this->hydrateWorkoutMedia($workout));
    }

    private function workoutScope(?int $tenantId, int $userId)
    {
        return Workout::query()
            ->where('user_id', $userId)
            ->when(
                $tenantId === null,
                fn($query) => $query->whereNull('tenant_id'),
                fn($query) => $query->where('tenant_id', $tenantId),
            );
    }

    private function hydrateWorkoutMedia(Workout $workout): Workout
    {
        $workoutPlan = $workout->workout_plan;

        if (! is_array($workoutPlan)) {
            return $workout;
        }

        if (! $this->workoutMediaService->workoutPlanNeedsMediaRefresh($workoutPlan)) {
            return $workout;
        }

        $workout->workout_plan = $this->workoutMediaService->enrichWorkoutPlan($workoutPlan);
        $workout->save();

        return $workout->fresh() ?? $workout;
    }
}
