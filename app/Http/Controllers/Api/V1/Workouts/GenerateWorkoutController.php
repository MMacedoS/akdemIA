<?php

namespace App\Http\Controllers\Api\V1\Workouts;

use App\Jobs\GenerateWorkoutJob;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\Workout\Workout;
use App\Services\Credits\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GenerateWorkoutController extends Controller
{
    public function __construct(
        private readonly CreditService $creditService,
    ) {}

    public function store(Request $request): JsonResponse
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

        $hasProcessingWorkout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'processing')
            ->exists();

        if ($hasProcessingWorkout) {
            return response()->json([
                'message' => 'Workout generation already in progress for this user.',
            ], 409);
        }

        try {
            $this->creditService->consumeCredits(
                $user,
                2,
                'consume_generation',
                [
                    'context' => 'api',
                    'tenant_id' => $tenant->id,
                ],
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $workout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'processing',
            'request_status' => 'active',
            'workout_plan' => ['weekly_plan' => []],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        GenerateWorkoutJob::dispatch($workout->id, $user->id, $tenant->id, null, $user->id);

        return response()->json([
            'status' => 'processing',
            'id' => $workout->id,
            'message' => 'Workout generation started.',
            'credits_balance' => (int) $user->fresh()?->credits_balance,
        ], 202);
    }
}
