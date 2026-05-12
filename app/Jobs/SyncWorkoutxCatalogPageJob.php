<?php

namespace App\Jobs;

use App\Services\System\SystemAdminAuditLogger;
use App\Services\System\SystemSettingsRuntimeService;
use App\Services\Workouts\ExerciseCatalogService;
use App\Services\Workouts\WorkoutMediaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncWorkoutxCatalogPageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $offset = 0,
        public readonly ?int $limit = null,
        public readonly ?int $requestedByUserId = null,
    ) {}

    public function handle(
        WorkoutMediaService $workoutMediaService,
        ExerciseCatalogService $exerciseCatalogService,
        SystemAdminAuditLogger $auditLogger,
        SystemSettingsRuntimeService $systemSettingsRuntimeService,
    ): void {
        $systemSettingsRuntimeService->apply();

        $workoutMediaService->markWorkoutxSyncRunning($this->offset);

        $pageResult = $workoutMediaService->syncExerciseCatalogPage($this->offset, $this->limit);
        $progress = $workoutMediaService->advanceWorkoutxSyncStatus($pageResult);

        if ($pageResult['has_more']) {
            self::dispatch(
                offset: (int) $pageResult['next_offset'],
                limit: (int) $pageResult['limit'],
                requestedByUserId: $this->requestedByUserId,
            )->delay(now()->addSeconds(max(10, (int) config('services.workoutx.sync_page_delay_seconds', 120))));

            return;
        }

        $exerciseCatalogService->exportAiCatalogDocument();
        $workoutMediaService->completeWorkoutxSyncStatus($progress);

        $auditLogger->log(
            $this->requestedByUserId,
            'workoutx_catalog',
            'synced',
            null,
            null,
            $progress,
        );
    }

    public function failed(Throwable $exception): void
    {
        app(WorkoutMediaService::class)->failWorkoutxSyncStatus((string) $exception->getMessage());

        app(SystemAdminAuditLogger::class)->log(
            $this->requestedByUserId,
            'workoutx_catalog',
            'sync_failed',
            null,
            null,
            [
                'offset' => $this->offset,
                'limit' => $this->limit,
                'error' => $exception->getMessage(),
            ],
        );
    }
}
