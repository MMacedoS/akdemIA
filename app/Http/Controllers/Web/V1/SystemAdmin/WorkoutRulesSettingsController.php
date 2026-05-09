<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SystemAdmin\WorkoutRulesSettingsRepositoryContract;
use App\Services\System\SystemAdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkoutRulesSettingsController extends Controller
{
    public function __construct(
        private readonly WorkoutRulesSettingsRepositoryContract $workoutRulesSettingsRepository,
        private readonly SystemAdminAuditLogger $auditLogger,
    ) {}

    public function edit(): View
    {
        return view('web.v1.system_admin.settings.workout-rules', [
            'settings' => $this->workoutRulesSettingsRepository->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $before = $this->workoutRulesSettingsRepository->values()->all();

        $validated = $request->validate([
            'workout_generate_cost' => ['required', 'integer', 'between:1,1000'],
            'workout_reuse_cost' => ['required', 'integer', 'between:1,1000'],
            'workout_reactivate_cost' => ['required', 'integer', 'between:1,1000'],
            'workout_active_days' => ['required', 'integer', 'between:1,365'],
        ]);

        $this->workoutRulesSettingsRepository->update($validated);

        $this->auditLogger->log(
            $request->user()?->id,
            'workout_rules',
            'updated',
            null,
            $before,
            $this->workoutRulesSettingsRepository->values()->all(),
        );

        return redirect()->route('system-admin.settings.workouts.edit')
            ->with('status', 'Regras de treino atualizadas.');
    }
}
