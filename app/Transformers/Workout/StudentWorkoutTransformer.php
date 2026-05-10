<?php

namespace App\Transformers\Workout;

use App\Models\Workout\Workout;
use Illuminate\Support\Collection;

class StudentWorkoutTransformer
{
    public function transform(Workout $workout): array
    {
        return [
            'id' => (int) $workout->id,
            'tenant_id' => $workout->tenant_id === null ? null : (int) $workout->tenant_id,
            'user_id' => (int) $workout->user_id,
            'status' => $workout->status,
            'request_status' => $workout->request_status,
            'regeneration_request' => $workout->regeneration_request,
            'activated_at' => $workout->activated_at?->toIso8601String(),
            'active_until_at' => $workout->active_until_at?->toIso8601String(),
            'workout_plan' => is_array($workout->workout_plan) ? $workout->workout_plan : [],
            'meal_plan' => is_array($workout->meal_plan) ? $workout->meal_plan : [],
            'recommendations' => is_array($workout->recommendations) ? $workout->recommendations : [],
            'cardio_plan' => is_array($workout->cardio_plan) ? $workout->cardio_plan : [],
            'safety_flags' => is_array($workout->safety_flags) ? $workout->safety_flags : [],
            'created_at' => $workout->created_at?->toIso8601String(),
            'updated_at' => $workout->updated_at?->toIso8601String(),
        ];
    }

    public function transformCollection(Collection $workouts): array
    {
        return $workouts
            ->map(fn(Workout $workout) => $this->transform($workout))
            ->values()
            ->all();
    }
}
