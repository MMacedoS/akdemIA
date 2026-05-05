<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            return response()->json([
                'message' => 'Tenant not identified.',
            ], 409);
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

        return response()->json([
            'data' => [
                'total_students' => $totalStudents,
                'total_trainers' => $totalTrainers,
                'ai_usage' => (int) ($subscription?->ai_usage ?? 0),
                'ai_limit' => (int) ($subscription?->plan?->ai_limit ?? 0),
                'credits_balance' => $creditsBalance,
                'current_plan' => $subscription?->plan?->name ?? 'Sem plano',
            ],
        ]);
    }
}
