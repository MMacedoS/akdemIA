<?php

namespace App\Http\Controllers\Api\V1\Students;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateWorkoutJob;
use App\Models\Tenant\Tenant;
use App\Models\Workout\Workout;
use App\Services\Credits\CreditService;
use App\Services\Workouts\WorkoutLifecycleService;
use App\Services\Workouts\WorkoutMediaService;
use App\Services\Workouts\WorkoutRulesService;
use App\Transformers\Workout\StudentWorkoutTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WorkoutController extends Controller
{
    public function __construct(
        private readonly WorkoutMediaService $workoutMediaService,
        private readonly CreditService $creditService,
        private readonly WorkoutRulesService $workoutRulesService,
        private readonly WorkoutLifecycleService $workoutLifecycleService,
        private readonly StudentWorkoutTransformer $studentWorkoutTransformer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $this->allowsStudentWorkoutContext($user, $tenant)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $tenant = $this->requireTenant($tenant);
        $this->workoutLifecycleService->expireExpiredWorkouts($tenant->id, (int) $user->id);

        $workouts = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', (int) $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn(Workout $workout) => $this->hydrateWorkoutMedia($workout));

        $currentWorkout = $this->resolveCurrentWorkout($tenant, (int) $user->id);

        return response()->json([
            'current_workout' => $currentWorkout === null
                ? null
                : $this->studentWorkoutTransformer->transform($currentWorkout),
            'data' => $this->studentWorkoutTransformer->transformCollection($workouts),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $this->allowsStudentWorkoutContext($user, $tenant)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $tenant = $this->requireTenant($tenant);
        $workout = $this->resolveCurrentWorkout($tenant, (int) $user->id);

        return response()->json([
            'data' => $workout === null
                ? null
                : $this->studentWorkoutTransformer->transform($workout),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $this->allowsStudentWorkoutContext($user, $tenant)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $tenant = $this->requireTenant($tenant);

        $hasProcessingWorkout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', (int) $user->id)
            ->where('status', 'processing')
            ->exists();

        if ($hasProcessingWorkout) {
            return response()->json([
                'message' => 'Ja existe uma geracao em processamento para voce.',
            ], 409);
        }

        try {
            $this->creditService->consumeCredits(
                $user,
                $this->workoutRulesService->generationCredits(),
                'consume_generation',
                [
                    'context' => 'api_student',
                    'tenant_id' => $tenant->id,
                    'student_id' => (int) $user->id,
                ],
                $tenant,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $workout = Workout::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'user_id' => (int) $user->id,
            'status' => 'processing',
            'workout_plan' => ['weekly_plan' => []],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ], $this->workoutLifecycleService->activeAttributes()));

        GenerateWorkoutJob::dispatch($workout->id, (int) $user->id, $tenant->id, null, (int) $user->id);

        return response()->json([
            'message' => 'Geracao do treino iniciada.',
            'credits_balance' => (int) $user->fresh()?->credits_balance,
            'data' => $this->studentWorkoutTransformer->transform($workout),
        ], 202);
    }

    private function allowsStudentWorkoutContext($user, mixed $tenant): bool
    {
        return $tenant instanceof Tenant
            && $user->profileType() === Role::STUDENT
            && $user->belongsToTenant($tenant);
    }

    private function requireTenant(mixed $tenant): Tenant
    {
        if (! $tenant instanceof Tenant) {
            abort(409, 'Tenant not identified.');
        }

        return $tenant;
    }

    private function resolveCurrentWorkout(Tenant $tenant, int $userId): ?Workout
    {
        $doneWorkout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->where('status', 'done')
            ->orderByDesc('id')
            ->first();

        if ($doneWorkout instanceof Workout) {
            return $this->hydrateWorkoutMedia($doneWorkout);
        }

        $workout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first();

        return $workout instanceof Workout ? $this->hydrateWorkoutMedia($workout) : null;
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
