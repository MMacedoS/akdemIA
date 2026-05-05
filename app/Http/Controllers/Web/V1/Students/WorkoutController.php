<?php

namespace App\Http\Controllers\Web\V1\Students;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\Workout\Workout;
use App\Services\Workouts\WorkoutMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkoutController extends Controller
{
    public function __construct(
        private readonly WorkoutMediaService $workoutMediaService,
    ) {}

    public function show(Request $request): View
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $workout = $this->resolveCurrentWorkout($tenant, (int) $user->id);

        return view('web.v1.students.workouts.show', [
            'workout' => $workout,
        ]);
    }

    public function start(Request $request): View
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $workout = $this->resolveCurrentWorkout($tenant, (int) $user->id);

        return view('web.v1.students.workouts.start', [
            'workout' => $workout,
        ]);
    }

    public function activate(Request $request, int $workoutId): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $workout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', (int) $user->id)
            ->where('id', $workoutId)
            ->firstOrFail();

        Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', (int) $user->id)
            ->where('id', '!=', $workout->id)
            ->where('request_status', 'active')
            ->update(['request_status' => 'inactive']);

        $workout->request_status = 'active';
        $workout->save();

        return redirect()->route('students.workout.show')
            ->with('status', 'Treino ativado com sucesso.');
    }

    public function inactivate(Request $request, int $workoutId): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $workout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', (int) $user->id)
            ->where('id', $workoutId)
            ->firstOrFail();

        $workout->request_status = 'inactive';
        $workout->save();

        return redirect()->route('students.workout.show')
            ->with('status', 'Treino inativado com sucesso.');
    }

    private function resolveTenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('tenant');

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
