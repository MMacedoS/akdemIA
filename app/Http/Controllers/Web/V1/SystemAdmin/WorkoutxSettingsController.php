<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncWorkoutxCatalogGifJob;
use App\Jobs\SyncWorkoutxCatalogPageJob;
use App\Models\AI\AiVectorStore;
use App\Repositories\Contracts\SystemAdmin\WorkoutxSettingsRepositoryContract;
use App\Services\System\SystemAdminAuditLogger;
use App\Services\Workouts\ExerciseCatalogService;
use App\Services\Workouts\WorkoutMediaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class WorkoutxSettingsController extends Controller
{
    public function __construct(
        private readonly WorkoutxSettingsRepositoryContract $workoutxSettingsRepository,
        private readonly ExerciseCatalogService $exerciseCatalogService,
        private readonly WorkoutMediaService $workoutMediaService,
        private readonly SystemAdminAuditLogger $auditLogger,
    ) {}

    public function edit(): View
    {
        $settings = $this->workoutxSettingsRepository->values();
        $catalogType = (string) ($settings->get('openai.vector_store.catalog_type') ?: config('services.openai.vector_store.catalog_type', 'workout_exercises'));

        return view('web.v1.system_admin.settings.workoutx', [
            'settings' => $settings,
            'catalogStats' => $this->workoutMediaService->catalogStats(),
            'syncStatus' => $this->workoutMediaService->workoutxSyncStatus(),
            'activeVectorStore' => AiVectorStore::query()
                ->where('catalog_type', $catalogType)
                ->latest('id')
                ->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $before = $this->workoutxSettingsRepository->values()->all();

        $validated = $request->validate([
            'workoutx_enabled' => ['nullable', 'in:0,1'],
            'workoutx_api_base_url' => ['nullable', 'url', 'max:2000'],
            'workoutx_api_key' => ['nullable', 'string', 'max:255'],
            'workoutx_auth_mode' => ['required', 'in:header,query'],
            'workoutx_request_timeout' => ['nullable', 'integer', 'between:3,120'],
            'workoutx_default_limit' => ['nullable', 'integer', 'between:1,100'],
            'workoutx_sync_page_delay_seconds' => ['nullable', 'integer', 'between:10,3600'],
            'workoutx_allow_fallback' => ['nullable', 'in:0,1'],
            'vector_store_enabled' => ['nullable', 'in:0,1'],
            'vector_store_scope' => ['required', 'in:global,tenant'],
            'vector_store_catalog_type' => ['required', 'string', 'max:80'],
            'vector_store_name_prefix' => ['required', 'string', 'max:120'],
            'vector_store_existing_id' => ['nullable', 'string', 'max:120'],
            'vector_store_existing_name' => ['nullable', 'string', 'max:160'],
            'vector_store_file_purpose' => ['required', 'string', 'max:80'],
            'vector_store_max_search_results' => ['nullable', 'integer', 'between:1,100'],
            'vector_store_minimum_candidates' => ['nullable', 'integer', 'between:1,100'],
            'vector_store_storage_path' => ['required', 'string', 'max:255'],
        ]);

        $validated['workoutx_enabled'] = $request->boolean('workoutx_enabled') ? '1' : '0';
        $validated['workoutx_allow_fallback'] = $request->boolean('workoutx_allow_fallback') ? '1' : '0';
        $validated['vector_store_enabled'] = $request->boolean('vector_store_enabled') ? '1' : '0';

        $this->workoutxSettingsRepository->update($validated);
        $this->bumpWorkoutxCacheBuster();

        $this->auditLogger->log(
            $request->user()?->id,
            'workoutx_settings',
            'updated',
            null,
            $before,
            $this->workoutxSettingsRepository->values()->all(),
        );

        return redirect()->route('system-admin.settings.workoutx.edit')
            ->with('status', 'Configuracoes da WorkoutX e Vector Store atualizadas.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $syncStatus = $this->workoutMediaService->workoutxSyncStatus();
        $syncState = (string) ($syncStatus['state'] ?? 'idle');

        if (in_array($syncState, ['queued', 'running'], true)) {
            return redirect()->route('system-admin.settings.workoutx.edit')
                ->withErrors('Ja existe uma sincronizacao do catalogo WorkoutX em andamento. Aguarde a fila terminar antes de iniciar outra.');
        }

        try {
            $this->workoutMediaService->assertCatalogSyncConfigured();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('system-admin.settings.workoutx.edit')
                ->withErrors($exception->getMessage());
        }

        $this->workoutMediaService->startWorkoutxSyncStatus($request->user()?->id);
        SyncWorkoutxCatalogPageJob::dispatch(0, null, $request->user()?->id);

        $this->auditLogger->log(
            $request->user()?->id,
            'workoutx_catalog',
            'queued',
            null,
            null,
            [
                'message' => 'Sincronizacao do catalogo WorkoutX enfileirada.',
            ],
        );

        return redirect()->route('system-admin.settings.workoutx.edit')
            ->with('status', 'Sincronizacao do catalogo enfileirada. O processamento vai ocorrer em paginas, com intervalo entre requests para evitar limite da API.');
    }

    public function audit(Request $request): View
    {
        return view('web.v1.system_admin.settings.workoutx-audit', [
            'audit' => $this->exerciseCatalogService->auditCatalog(
                $request->query('focus'),
                $request->query('search'),
                $request->query('translation_status'),
                (int) $request->query('limit', 25),
                (int) $request->query('page', 1),
            ),
            'gifCatalogStats' => $this->workoutMediaService->catalogGifStats(),
            'gifSyncStatus' => $this->workoutMediaService->workoutxGifSyncStatus(),
        ]);
    }

    public function syncGifs(Request $request): RedirectResponse
    {
        $gifSyncStatus = $this->workoutMediaService->workoutxGifSyncStatus();
        $gifSyncState = (string) ($gifSyncStatus['state'] ?? 'idle');

        if (in_array($gifSyncState, ['queued', 'running'], true)) {
            return redirect()->route('system-admin.settings.workoutx.audit')
                ->withErrors('Ja existe uma sincronizacao de GIFs do catalogo WorkoutX em andamento. Aguarde a fila terminar antes de iniciar outra.');
        }

        $this->workoutMediaService->startWorkoutxGifSyncStatus($request->user()?->id);
        SyncWorkoutxCatalogGifJob::dispatch(null, null, $request->user()?->id);

        $this->auditLogger->log(
            $request->user()?->id,
            'workoutx_catalog_gifs',
            'queued',
            null,
            null,
            [
                'message' => 'Sincronizacao dos GIFs pendentes do catalogo WorkoutX enfileirada.',
            ],
        );

        return redirect()->route('system-admin.settings.workoutx.audit')
            ->with('status', 'Download dos GIFs pendentes enfileirado. O processamento vai preencher o storage_path a partir do remote_gif_url.');
    }

    private function bumpWorkoutxCacheBuster(): void
    {
        $cacheKey = 'workoutx:cache_buster';

        if (Cache::has($cacheKey)) {
            Cache::increment($cacheKey);

            return;
        }

        Cache::forever($cacheKey, 1);
    }
}
