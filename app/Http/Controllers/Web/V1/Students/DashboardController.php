<?php

namespace App\Http\Controllers\Web\V1\Students;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\Workout\Workout;
use App\Repositories\Contracts\Tenant\TraineeStudentRepositoryContract;
use App\Services\Workouts\WorkoutInsightsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TraineeStudentRepositoryContract $traineeStudentRepository,
        private readonly WorkoutInsightsService $workoutInsightsService,
    ) {}

    public function index(Request $request): View
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $latestWorkout = $tenant instanceof Tenant
            ? Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first(['id', 'status', 'created_at', 'workout_plan'])
            : null;
        $recentDoneWorkouts = $tenant instanceof Tenant
            ? Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'done')
            ->orderByDesc('id')
            ->limit(3)
            ->get(['id', 'status', 'created_at', 'workout_plan'])
            : collect();

        $completedProfileItems = collect([
            $user->goal,
            optional($user->physicalData)->activity_level,
            optional($user->medicalData)->restrictions,
            optional($user->preference)->training_frequency,
        ])->filter(fn($value) => filled($value))->count();

        $assignedTrainee = $this->traineeStudentRepository->assignedTraineeForStudent($tenant, (int) $user->id);

        return view('web.v1.students.dashboard', [
            'summary' => [
                'profile_completion' => $completedProfileItems,
                'latest_workout_status' => $latestWorkout?->status ?? 'nao gerado',
                'latest_workout_date' => optional($latestWorkout?->created_at)?->format('d/m/Y H:i'),
                'has_tenant' => $tenant instanceof Tenant,
            ],
            'assignedTrainee' => $assignedTrainee,
            'workoutStatistics' => $this->workoutInsightsService->aggregate($recentDoneWorkouts),
        ]);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $tenant = $request->attributes->get('tenant');

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
