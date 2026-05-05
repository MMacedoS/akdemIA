<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Billing\UpgradePlanRequest;
use App\Models\Tenant\Plan;
use App\Models\Tenant\Tenant;
use App\Services\Billing\StripeBillingService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class UpgradePlanController extends Controller
{
    public function __construct(
        private readonly StripeBillingService $stripeBillingService,
    ) {}

    public function store(UpgradePlanRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $tenant instanceof Tenant || ! $user->belongsToTenant($tenant)) {
            return response()->json([
                'message' => 'Forbidden for tenant context.',
            ], 403);
        }

        $plan = Plan::query()->find($request->integer('plan_id'));

        if ($plan === null) {
            return response()->json([
                'message' => 'Plan not found.',
            ], 404);
        }

        try {
            $updatedSubscription = $this->stripeBillingService->upgradeSubscriptionPlan($tenant, $plan);

            return response()->json([
                'message' => 'Plan upgraded successfully.',
                'stripe_subscription_id' => (string) data_get($updatedSubscription, 'id', ''),
                'status' => (string) data_get($updatedSubscription, 'status', ''),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
