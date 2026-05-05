<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsageController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            return response()->json([
                'message' => 'Tenant not identified.',
            ], 409);
        }

        $currentSubscription = TenantSubscription::query()
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

        if ($currentSubscription === null) {
            $currentSubscription = TenantSubscription::query()
                ->with('plan')
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('id')
                ->first();
        }

        $plan = $currentSubscription?->plan;
        $aiUsage = (int) ($currentSubscription?->ai_usage ?? 0);
        $aiLimit = (int) ($plan?->ai_limit ?? 0);

        $studentsUsed = DB::table('tenant_user')
            ->where('tenant_id', $tenant->id)
            ->where('role', Role::STUDENT->value)
            ->count();

        $creditsBalance = (int) DB::table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->where('tenant_user.tenant_id', $tenant->id)
            ->sum('users.credits_balance');

        $studentsLimit = (int) ($plan?->max_students ?? 0);
        $aiConsumedPercentage = $aiLimit > 0
            ? round(($aiUsage / $aiLimit) * 100, 2)
            : 0.0;

        return response()->json([
            'current_plan' => $plan?->name,
            'ai_usage' => $aiUsage,
            'ai_limit' => $aiLimit,
            'ai_consumed_percentage' => $aiConsumedPercentage,
            'credits_balance' => $creditsBalance,
            'students_used' => $studentsUsed,
            'students_limit' => $studentsLimit,
        ]);
    }
}
