<?php

namespace App\Services\AI;

use App\Models\Tenant\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AiUsageService
{
    public function assertCanUseAi(Tenant $tenant): void
    {
        $subscription = $this->activeSubscriptionWithPlan($tenant);

        if ($subscription === null) {
            throw new RuntimeException('No active subscription found for tenant.');
        }

        if ((int) $subscription->ai_usage >= (int) $subscription->ai_limit) {
            throw new RuntimeException('AI monthly limit exceeded for tenant plan.');
        }
    }

    public function incrementUsage(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            $subscription = DB::table('tenant_subscriptions as ts')
                ->join('plans as p', 'p.id', '=', 'ts.plan_id')
                ->where('ts.tenant_id', $tenant->id)
                ->where('ts.status', 'active')
                ->where('ts.starts_at', '<=', now())
                ->where(function ($query): void {
                    $query->whereNull('ts.ends_at')
                        ->orWhere('ts.ends_at', '>=', now());
                })
                ->lockForUpdate()
                ->select(['ts.id', 'ts.ai_usage', 'p.ai_limit'])
                ->first();

            if ($subscription === null) {
                throw new RuntimeException('No active subscription found for tenant.');
            }

            if ((int) $subscription->ai_usage >= (int) $subscription->ai_limit) {
                throw new RuntimeException('AI monthly limit exceeded for tenant plan.');
            }

            DB::table('tenant_subscriptions')
                ->where('id', $subscription->id)
                ->update([
                    'ai_usage' => (int) $subscription->ai_usage + 1,
                ]);
        }, 3);
    }

    public function resetMonthlyUsage(?CarbonImmutable $reference = null): int
    {
        $now = $reference ?? CarbonImmutable::now();

        return DB::table('tenant_subscriptions')
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->update(['ai_usage' => 0]);
    }

    private function activeSubscriptionWithPlan(Tenant $tenant): ?object
    {
        return DB::table('tenant_subscriptions as ts')
            ->join('plans as p', 'p.id', '=', 'ts.plan_id')
            ->where('ts.tenant_id', $tenant->id)
            ->where('ts.status', 'active')
            ->where('ts.starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ts.ends_at')
                    ->orWhere('ts.ends_at', '>=', now());
            })
            ->select(['ts.id', 'ts.ai_usage', 'p.ai_limit'])
            ->first();
    }
}
