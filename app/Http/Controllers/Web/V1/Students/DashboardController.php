<?php

namespace App\Http\Controllers\Web\V1\Students;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\Workout\Workout;
use App\Repositories\Contracts\Tenant\TraineeStudentRepositoryContract;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TraineeStudentRepositoryContract $traineeStudentRepository,
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
            ->first(['id', 'status', 'created_at'])
            : null;

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
        ]);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $tenant = $request->attributes->get('tenant');

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
