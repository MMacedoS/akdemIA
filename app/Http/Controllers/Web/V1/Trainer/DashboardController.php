<?php

namespace App\Http\Controllers\Web\V1\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use App\Repositories\Contracts\Tenant\TraineeStudentRepositoryContract;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TraineeStudentRepositoryContract $repository,
    ) {}

    public function index(Request $request): View
    {
        $this->resolveTenant($request);
        $trainer = $request->user();

        abort_unless($trainer instanceof User, 401, 'Sessao invalida. Faca login novamente.');

        $metrics = $this->repository->metricsForTrainee(null, $trainer->id);

        $pendingWorkouts = Workout::query()
            ->whereNull('tenant_id')
            ->where('user_id', 'in', function ($query) use ($trainer): void {
                $query->select('student_user_id')
                    ->from('tenant_student_trainee_links')
                    ->where('trainee_user_id', $trainer->id)
                    ->whereNull('tenant_id');
            })
            ->where('status', 'processing')
            ->count();

        $completedWorkouts = Workout::query()
            ->whereNull('tenant_id')
            ->where('user_id', 'in', function ($query) use ($trainer): void {
                $query->select('student_user_id')
                    ->from('tenant_student_trainee_links')
                    ->where('trainee_user_id', $trainer->id)
                    ->whereNull('tenant_id');
            })
            ->where('status', 'done')
            ->count();

        $recentStudents = $this->repository->recentForTrainee(null, $trainer->id, 8);

        return view('web.v1.trainer.dashboard', [
            'summary' => [
                'students' => $metrics['total'],
                'pending_workouts' => $pendingWorkouts,
                'completed_workouts' => $completedWorkouts,
            ],
            'recentStudents' => $recentStudents,
        ]);
    }

    private function resolveTenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            abort(409, 'Tenant not identified.');
        }

        return $tenant;
    }
}
