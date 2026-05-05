<?php

namespace App\Jobs;

use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use App\Notifications\WorkoutGenerationFinishedNotification;
use App\Services\System\SystemSettingsRuntimeService;
use App\Services\Workouts\WorkoutGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class GenerateWorkoutJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 90];

    public function __construct(
        public readonly int $workoutId,
        public readonly int $userId,
        public readonly ?int $tenantId,
        public readonly ?string $adjustmentRequest = null,
        public readonly ?int $requestedByUserId = null,
    ) {}

    public function handle(
        WorkoutGenerationService $workoutGenerationService,
        SystemSettingsRuntimeService $systemSettingsRuntimeService,
    ): void {
        $systemSettingsRuntimeService->apply();

        $targetWorkout = Workout::query()->find($this->workoutId);
        $user = User::query()->find($this->userId);
        $tenant = $this->tenantId !== null ? Tenant::query()->find($this->tenantId) : null;
        $requester = $this->resolveRequester($user);

        if ($targetWorkout === null || $user === null) {
            return;
        }

        $hasInvalidContext = $tenant instanceof Tenant
            ? (! $user->belongsToTenant($tenant)
                || (int) $targetWorkout->tenant_id !== $tenant->id
                || (int) $targetWorkout->user_id !== $user->id)
            : ($targetWorkout->tenant_id !== null || (int) $targetWorkout->user_id !== $user->id);

        if ($hasInvalidContext) {
            $targetWorkout->status = 'error';
            $targetWorkout->safety_flags = [
                'generation_error' => 'Invalid workout context for generation.',
            ];
            $targetWorkout->save();
            $this->notifyFailure($requester, $targetWorkout, 'Falha de contexto na geracao do treino.');
            return;
        }

        try {
            $generatedWorkout = $workoutGenerationService->generate($user, $tenant, $this->adjustmentRequest);

            $targetWorkout->fill([
                'status' => 'done',
                'workout_plan' => $generatedWorkout->workout_plan,
                'meal_plan' => $generatedWorkout->meal_plan,
                'recommendations' => $generatedWorkout->recommendations,
                'cardio_plan' => $generatedWorkout->cardio_plan,
                'safety_flags' => $generatedWorkout->safety_flags,
            ]);
            $targetWorkout->save();

            if ($generatedWorkout->id !== $targetWorkout->id) {
                $generatedWorkout->delete();
            }

            $this->notifySuccess($requester, $targetWorkout);
        } catch (Throwable $exception) {
            report($exception);

            if ($this->shouldRetry($exception)) {
                throw $exception;
            }

            $targetWorkout->status = 'error';
            $targetWorkout->safety_flags = [
                'generation_error' => Str::limit($exception->getMessage(), 500),
            ];
            $targetWorkout->save();

            $this->notifyFailure(
                $requester,
                $targetWorkout,
                (string) Str::limit($exception->getMessage(), 500)
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        $targetWorkout = Workout::query()->find($this->workoutId);
        $user = User::query()->find($this->userId);
        $requester = $this->resolveRequester($user);

        if ($targetWorkout === null) {
            return;
        }

        $targetWorkout->status = 'error';
        $targetWorkout->safety_flags = [
            'generation_error' => Str::limit($exception->getMessage(), 500),
        ];
        $targetWorkout->save();

        $this->notifyFailure(
            $requester,
            $targetWorkout,
            (string) Str::limit($exception->getMessage(), 500)
        );
    }

    private function shouldRetry(Throwable $exception): bool
    {
        $message = mb_strtolower((string) $exception->getMessage());

        return str_contains($message, 'tempo limite excedido')
            || str_contains($message, 'falha de conexao')
            || str_contains($message, 'curl error 28')
            || str_contains($message, 'timed out');
    }

    private function resolveRequester(?User $fallbackUser): ?User
    {
        if ($this->requestedByUserId !== null) {
            $requester = User::query()->find($this->requestedByUserId);
            if ($requester instanceof User) {
                return $requester;
            }
        }

        return $fallbackUser;
    }

    private function notifySuccess(?User $requester, Workout $workout): void
    {
        if (! $requester instanceof User) {
            return;
        }

        $requester->notify(new WorkoutGenerationFinishedNotification(
            workoutId: $workout->id,
            status: 'done',
            message: 'Seu treino #' . $workout->id . ' foi gerado com sucesso.'
        ));
    }

    private function notifyFailure(?User $requester, Workout $workout, string $errorMessage): void
    {
        if (! $requester instanceof User) {
            return;
        }

        $requester->notify(new WorkoutGenerationFinishedNotification(
            workoutId: $workout->id,
            status: 'error',
            message: 'A geracao do treino #' . $workout->id . ' falhou: ' . $errorMessage
        ));
    }
}
