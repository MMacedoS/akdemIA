<?php

namespace App\Http\Controllers\Web\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            abort(409, 'Tenant not identified.');
        }

        $subscription = TenantSubscription::query()
            ->with('plan')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('id')
            ->first();

        if ($subscription === null) {
            $subscription = TenantSubscription::query()
                ->with('plan')
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('id')
                ->first();
        }

        $totalStudents = DB::table('tenant_user')
            ->where('tenant_id', $tenant->id)
            ->where('role', Role::STUDENT->value)
            ->count();

        $totalTrainers = DB::table('tenant_user')
            ->where('tenant_id', $tenant->id)
            ->where('role', Role::TRAINER->value)
            ->count();

        $creditsBalance = (int) DB::table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->where('tenant_user.tenant_id', $tenant->id)
            ->sum('users.credits_balance');

        return view('web.v1.admin.dashboard.index', [
            'summary' => [
                'total_students' => $totalStudents,
                'total_trainers' => $totalTrainers,
                'credits_balance' => $creditsBalance,
                'current_plan' => $subscription?->plan?->name ?? 'Sem plano',
            ],
        ]);
    }
}
