<?php

namespace App\Http\Middleware;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSubscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            return response()->json([
                'message' => 'Tenant not identified.',
            ], 409);
        }

        $activeSubscriptionExists = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->exists();

        if (! $activeSubscriptionExists) {
            return response()->json([
                'message' => 'Inactive subscription. Payment required.',
            ], 402);
        }

        return $next($request);
    }
}
