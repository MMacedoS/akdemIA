<?php

namespace App\Http\Controllers\Api\V1\Workouts;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\Workout\Workout;
use App\Services\Credits\CreditService;
use App\Services\Workouts\WorkoutLifecycleService;
use App\Services\Workouts\WorkoutRulesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class ChangeWorkoutStatusController extends Controller
{
    public function __construct(
        private readonly CreditService $creditService,
        private readonly WorkoutRulesService $workoutRulesService,
        private readonly WorkoutLifecycleService $workoutLifecycleService,
    ) {}

    public function update(Request $request, int $workoutId): JsonResponse
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

        $payload = $request->validate([
            'request_status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $workout = Workout::query()
            ->where('id', $workoutId)
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

        $workout = $this->workoutLifecycleService->syncWorkoutStatus($workout);

        if ($payload['request_status'] === 'inactive') {
            if ((string) $workout->request_status !== 'inactive') {
                $workout = $this->workoutLifecycleService->inactivateWorkout($workout);
            }

            return response()->json([
                'message' => 'Treino inativado com sucesso.',
                'credits_balance' => (int) $user->fresh()?->credits_balance,
                'data' => [
                    'id' => $workout->id,
                    'status' => $workout->status,
                    'request_status' => $workout->request_status,
                ],
            ]);
        }

        if ((string) $workout->request_status === 'active') {
            return response()->json([
                'message' => 'Treino ja esta ativo.',
                'credits_balance' => (int) $user->fresh()?->credits_balance,
                'data' => [
                    'id' => $workout->id,
                    'status' => $workout->status,
                    'request_status' => $workout->request_status,
                ],
            ]);
        }

        try {
            $this->creditService->consumeCredits(
                $user,
                $this->workoutRulesService->reactivationCredits(),
                'consume_reactivation',
                [
                    'context' => 'api',
                    'tenant_id' => $tenant?->id,
                    'student_id' => (int) $user->id,
                    'workout_id' => $workout->id,
                ],
                $tenant instanceof Tenant ? $tenant : null,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $workout = $this->workoutLifecycleService->activateWorkout(
            Workout::query()
                ->where('user_id', $user->id)
                ->when(
                    $tenant instanceof Tenant,
                    fn($query) => $query->where('tenant_id', $tenant->id),
                    fn($query) => $query->whereNull('tenant_id'),
                ),
            $workout,
        );

        return response()->json([
            'message' => 'Treino ativado com sucesso.',
            'credits_balance' => (int) $user->fresh()?->credits_balance,
            'data' => [
                'id' => $workout->id,
                'status' => $workout->status,
                'request_status' => $workout->request_status,
            ],
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
