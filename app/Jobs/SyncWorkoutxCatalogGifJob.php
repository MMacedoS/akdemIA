<?php

namespace App\Jobs;

use App\Services\System\SystemAdminAuditLogger;
use App\Services\System\SystemSettingsRuntimeService;
use App\Services\Workouts\WorkoutMediaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncWorkoutxCatalogGifJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly ?string $afterRemoteExerciseId = null,
        public readonly ?int $limit = null,
        public readonly ?int $requestedByUserId = null,
    ) {}

    public function handle(
        WorkoutMediaService $workoutMediaService,
        SystemAdminAuditLogger $auditLogger,
        SystemSettingsRuntimeService $systemSettingsRuntimeService,
    ): void {
        $systemSettingsRuntimeService->apply();

        $workoutMediaService->markWorkoutxGifSyncRunning($this->afterRemoteExerciseId);

        $batchResult = $workoutMediaService->syncPendingCatalogGifs($this->afterRemoteExerciseId, $this->limit);
        $progress = $workoutMediaService->advanceWorkoutxGifSyncStatus($batchResult);

        if ($batchResult['has_more']) {
            self::dispatch(
                afterRemoteExerciseId: (string) $batchResult['next_remote_exercise_id'],
                limit: (int) $batchResult['limit'],
                requestedByUserId: $this->requestedByUserId,
            );

            return;
        }

        $workoutMediaService->completeWorkoutxGifSyncStatus($progress);

        $auditLogger->log(
            $this->requestedByUserId,
            'workoutx_catalog_gifs',
            'synced',
            null,
            null,
            $progress,
        );
    }

    public function failed(Throwable $exception): void
    {
        app(WorkoutMediaService::class)->failWorkoutxGifSyncStatus((string) $exception->getMessage());

        app(SystemAdminAuditLogger::class)->log(
            $this->requestedByUserId,
            'workoutx_catalog_gifs',
            'sync_failed',
            null,
            null,
            [
                'after_remote_exercise_id' => $this->afterRemoteExerciseId,
                'limit' => $this->limit,
                'error' => $exception->getMessage(),
            ],
        );
    }
}
