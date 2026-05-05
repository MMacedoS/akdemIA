<?php

namespace App\Http\Controllers\Api\V1\Workouts;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Services\Workouts\WorkoutMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciseLookupController extends Controller
{
    public function __construct(
        private readonly WorkoutMediaService $workoutMediaService,
    ) {}

    public function show(Request $request, string $name): JsonResponse
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

        $queryName = trim($name);

        if ($queryName === '') {
            return response()->json([
                'message' => 'Exercise name is required.',
            ], 422);
        }

        $lookup = $this->workoutMediaService->lookupExerciseByName($queryName);

        if (! (bool) ($lookup['found'] ?? false)) {
            return response()->json([
                'message' => 'Exercise not found in WorkoutX.',
                'query' => $queryName,
                'workoutx_name' => $lookup['workoutx_name'] ?? null,
            ], 404);
        }

        return response()->json($lookup);
    }
}
