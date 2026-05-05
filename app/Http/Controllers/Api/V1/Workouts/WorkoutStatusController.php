<?php

namespace App\Http\Controllers\Api\V1\Workouts;

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

        if (! $tenant instanceof Tenant || ! $user->belongsToTenant($tenant)) {
            return response()->json([
                'message' => 'Forbidden for tenant context.',
            ], 403);
        }

        $workout = Workout::query()
            ->where('id', $id)
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
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
}
