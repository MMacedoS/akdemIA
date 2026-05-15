<?php

namespace App\Jobs;

use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use App\Notifications\WorkoutGenerationFinishedNotification;
use App\Services\Credits\CreditService;
use App\Services\System\SystemSettingsRuntimeService;
use App\Services\Workouts\WorkoutGenerationCooldownService;
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
        CreditService $creditService,
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
            $this->refundCreditsAndDeleteFailedWorkout(
                $targetWorkout,
                $tenant,
                $creditService,
                $requester,
                'Falha de contexto na geracao do treino.'
            );
            return;
        }

        try {
            $generatedWorkout = $workoutGenerationService->generatePayload($user, $tenant, $this->adjustmentRequest);

            $targetWorkout->fill([
                'status' => 'done',
                'workout_plan' => $generatedWorkout['workout_plan'],
                'meal_plan' => $generatedWorkout['meal_plan'],
                'recommendations' => $generatedWorkout['recommendations'],
                'cardio_plan' => $generatedWorkout['cardio_plan'],
                'safety_flags' => $generatedWorkout['safety_flags'],
            ]);
            $targetWorkout->save();

            $this->notifySuccess($requester, $targetWorkout);
        } catch (Throwable $exception) {
            report($exception);

            if ($this->shouldRetry($exception)) {
                throw $exception;
            }

            $this->refundCreditsAndDeleteFailedWorkout(
                $targetWorkout,
                $tenant,
                $creditService,
                $requester,
                (string) Str::limit($exception->getMessage(), 500)
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        $targetWorkout = Workout::query()->find($this->workoutId);
        $user = User::query()->find($this->userId);
        $tenant = $this->tenantId !== null ? Tenant::query()->find($this->tenantId) : null;
        $requester = $this->resolveRequester($user);

        if ($targetWorkout === null) {
            return;
        }

        $this->refundCreditsAndDeleteFailedWorkout(
            $targetWorkout,
            $tenant,
            app(CreditService::class),
            $requester,
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

    private function notifyFailure(?User $requester, int $workoutId, string $errorMessage): void
    {
        if (! $requester instanceof User) {
            return;
        }

        $requester->notify(new WorkoutGenerationFinishedNotification(
            workoutId: $workoutId,
            status: 'error',
            message: 'A geracao do treino #' . $workoutId . ' falhou: ' . $errorMessage . ' Os creditos foram devolvidos e o treino foi removido.'
        ));
    }

    private function refundCreditsAndDeleteFailedWorkout(
        Workout $workout,
        ?Tenant $tenant,
        CreditService $creditService,
        ?User $requester,
        string $errorMessage,
    ): void {
        $workoutId = (int) $workout->id;
        $friendlyErrorMessage = app(WorkoutGenerationCooldownService::class)->localizeFailureMessage($errorMessage);

        $this->refundCreditsIfNeeded($workout, $tenant, $creditService, $friendlyErrorMessage);
        $workout->delete();

        $this->notifyFailure($requester, $workoutId, $friendlyErrorMessage);
    }

    private function refundCreditsIfNeeded(Workout $workout, ?Tenant $tenant, CreditService $creditService, string $errorMessage): void
    {
        $transactionId = (int) data_get($workout->safety_flags, 'credit_charge.transaction_id', 0);

        if ($transactionId <= 0) {
            return;
        }

        $chargeTransaction = \App\Models\Credits\CreditTransaction::query()->find($transactionId);

        if (! $chargeTransaction instanceof \App\Models\Credits\CreditTransaction) {
            return;
        }

        $alreadyRefunded = \App\Models\Credits\CreditTransaction::query()
            ->where('type', 'refund_workout_error')
            ->get()
            ->contains(fn(\App\Models\Credits\CreditTransaction $transaction): bool => (int) data_get($transaction->metadata, 'refunded_transaction_id', 0) === $chargeTransaction->id);

        if ($alreadyRefunded) {
            return;
        }

        $chargedUser = User::query()->find($chargeTransaction->user_id);

        if (! $chargedUser instanceof User) {
            return;
        }

        $cooldownService = app(WorkoutGenerationCooldownService::class);

        $creditService->addCredits(
            $chargedUser,
            abs((int) $chargeTransaction->amount),
            null,
            'refund_workout_error',
            'Estorno automatico por falha na geracao do treino.',
            array_merge([
                'workout_id' => $workout->id,
                'refunded_transaction_id' => $chargeTransaction->id,
                'refunded_transaction_type' => $chargeTransaction->type,
            ], $cooldownService->cooldownMetadata($tenant, (int) $workout->user_id, $errorMessage)),
            $tenant,
        );
    }
}
