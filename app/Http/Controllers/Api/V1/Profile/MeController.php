<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateMeRequest;
use App\Services\Workouts\CurrentWorkoutResolver;
use App\Services\Workouts\WorkoutInsightsService;
use App\Services\Students\StudentProfileService;
use App\Transformers\Workout\StudentWorkoutTransformer;
use App\Transformers\Workout\WorkoutInsightsTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(
        private readonly StudentProfileService $studentProfileService,
        private readonly CurrentWorkoutResolver $currentWorkoutResolver,
        private readonly WorkoutInsightsService $workoutInsightsService,
        private readonly StudentWorkoutTransformer $studentWorkoutTransformer,
        private readonly WorkoutInsightsTransformer $workoutInsightsTransformer,
    ) {}

    public function show(Request $request): JsonResponse
    {
        if (! $this->studentProfileService->profileColumnsReady()) {
            return response()->json([
                'message' => 'Profile fields are not available yet. Run migrations.',
            ], 503);
        }

        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $this->studentProfileService->allowsSelfService($user, $tenant)) {
            return response()->json([
                'message' => 'User is not linked to tenant.',
            ], 403);
        }

        $payload = $this->studentProfileService->profilePayload($user, $tenant);
        $payload['current_workout'] = $this->resolveCurrentWorkoutPayload($user, $tenant);
        $payload['workout_statistics'] = $this->resolveWorkoutStatisticsPayload($user, $tenant);

        return response()->json($payload);
    }

    public function update(UpdateMeRequest $request): JsonResponse
    {
        if (! $this->studentProfileService->profileColumnsReady()) {
            return response()->json([
                'message' => 'Profile fields are not available yet. Run migrations.',
            ], 503);
        }

        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $this->studentProfileService->allowsSelfService($user, $tenant)) {
            return response()->json([
                'message' => 'User is not linked to tenant.',
            ], 403);
        }

        $user = $this->studentProfileService->updateProfile($user, $request->validated());

        $payload = $this->studentProfileService->profilePayload($user, $tenant);
        $payload['current_workout'] = $this->resolveCurrentWorkoutPayload($user, $tenant);
        $payload['workout_statistics'] = $this->resolveWorkoutStatisticsPayload($user, $tenant);

        return response()->json($payload);
    }

    private function resolveCurrentWorkoutPayload($user, mixed $tenant): ?array
    {
        if ($user->profileType()?->value !== 'student') {
            return null;
        }

        $workout = $this->currentWorkoutResolver->resolveForUser($user, $tenant);

        return $workout === null
            ? null
            : $this->studentWorkoutTransformer->transform($workout);
    }

    private function resolveWorkoutStatisticsPayload($user, mixed $tenant): array
    {
        if ($user->profileType()?->value !== 'student') {
            return [];
        }

        $workouts = $this->currentWorkoutResolver->recentDoneWorkoutsForUser($user, $tenant, 3);

        return $this->workoutInsightsTransformer->transformAggregate(
            $this->workoutInsightsService->aggregate($workouts),
        );
    }
}
