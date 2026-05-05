<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SystemAdmin\WorkoutxSettingsRepositoryContract;
use App\Services\System\SystemAdminAuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkoutxSettingsController extends Controller
{
    public function __construct(
        private readonly WorkoutxSettingsRepositoryContract $workoutxSettingsRepository,
        private readonly SystemAdminAuditLogger $auditLogger,
    ) {}

    public function edit(): View
    {
        return view('web.v1.system_admin.settings.workoutx', [
            'settings' => $this->workoutxSettingsRepository->values(),
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
            'workoutx_allow_fallback' => ['nullable', 'in:0,1'],
        ]);

        $validated['workoutx_enabled'] = $request->boolean('workoutx_enabled') ? '1' : '0';
        $validated['workoutx_allow_fallback'] = $request->boolean('workoutx_allow_fallback') ? '1' : '0';

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
            ->with('status', 'Configuracoes da WorkoutX atualizadas.');
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
