<?php

namespace App\Http\Controllers\Api\V1\Workouts;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\Workout\Workout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkoutStatusController extends Controller
{
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $this->allowsWorkoutContext($user, $tenant)) {
            return response()->json([
                'message' => 'Forbidden for tenant context.',
            ], 403);
        }

        $workout = Workout::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->when(
                $tenant instanceof Tenant,
                fn($query) => $query->where('tenant_id', $tenant->id),
                fn($query) => $query->whereNull('tenant_id'),
            )
            ->first();

        if ($workout === null) {
            return response()->json([
                'message' => 'Workout not found.',
            ], 404);
        }

        return response()->json([
            'status' => $workout->status,
            'request_status' => $workout->request_status,
            'result' => $workout->status === 'done' ? [
                'id' => $workout->id,
                'tenant_id' => $workout->tenant_id,
                'user_id' => $workout->user_id,
                'workout_plan' => $workout->workout_plan,
                'meal_plan' => $workout->meal_plan,
                'recommendations' => $workout->recommendations,
                'cardio_plan' => $workout->cardio_plan,
                'safety_flags' => $workout->safety_flags,
            ] : null,
            'error' => $workout->status === 'error'
                ? data_get($workout->safety_flags, 'generation_error')
                : null,
        ]);
    }

    private function allowsWorkoutContext($user, mixed $tenant): bool
    {
        if ($tenant instanceof Tenant) {
            return $user->belongsToTenant($tenant);
        }

        return $user->profileType() === Role::STUDENT;
    }
}
