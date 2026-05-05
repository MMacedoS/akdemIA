<?php

namespace App\Http\Controllers\Web\V1\Trainer;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\Workout\Workout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->resolveTenant($request);

        $studentsCount = DB::table('tenant_user')
            ->where('tenant_id', $tenant->id)
            ->where('role', Role::STUDENT->value)
            ->count();

        $pendingWorkouts = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->count();

        $completedWorkouts = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->count();

        $recentStudents = $tenant->users()
            ->wherePivot('role', Role::STUDENT->value)
            ->select('users.id', 'users.name', 'users.email', 'users.goal', 'users.created_at')
            ->orderByDesc('users.created_at')
            ->limit(8)
            ->get();

        return view('web.v1.trainer.dashboard', [
            'summary' => [
                'students' => $studentsCount,
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
