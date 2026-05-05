<?php

namespace App\Http\Controllers\Web\V1\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $trainee = $request->user();
        abort_unless($trainee instanceof User && $trainee->isTrainee(), 403, 'Acesso permitido apenas para perfil trainee.');

        $tenant = $request->attributes->get('tenant');
        if (! $tenant instanceof Tenant) {
            $tenant = null;
        }

        $studentIdsQuery = DB::table('tenant_student_trainee_links')
            ->where('trainee_user_id', $trainee->id);

        if ($tenant instanceof Tenant) {
            $studentIdsQuery->where('tenant_id', $tenant->id);
        } else {
            $studentIdsQuery->whereNull('tenant_id');
        }

        $studentIds = (clone $studentIdsQuery)
            ->distinct()
            ->pluck('student_user_id');

        $workoutsQuery = Workout::query()->whereIn('user_id', $studentIds->all());

        if ($tenant instanceof Tenant) {
            $workoutsQuery->where('tenant_id', $tenant->id);
        } else {
            $workoutsQuery->whereNull('tenant_id');
        }

        $recentStudents = User::query()
            ->whereIn('id', $studentIds->take(8)->all())
            ->select('id', 'name', 'email', 'goal', 'created_at')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return view('web.v1.trainee.dashboard', [
            'summary' => [
                'students' => $studentIds->count(),
                'pending_workouts' => (clone $workoutsQuery)->where('status', 'processing')->count(),
                'completed_workouts' => (clone $workoutsQuery)->where('status', 'done')->count(),
                'credits_balance' => (int) $trainee->credits_balance,
            ],
            'recentStudents' => $recentStudents,
            'activeContextLabel' => $tenant?->name ?? 'Carteira pessoal do trainee',
            'tenant' => $tenant,
        ]);
    }
}
