<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Billing\CreateSubscriptionRequest;
use App\Models\Tenant\Plan;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSubscription;
use App\Services\Billing\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly StripeBillingService $stripeBillingService,
    ) {}

    public function store(CreateSubscriptionRequest $request): JsonResponse
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
            $this->stripeBillingService->createCustomer(
                $tenant,
                (string) $user->email,
                $tenant->name,
            );

            $stripeSubscription = $this->stripeBillingService->createSubscription(
                $tenant,
                $plan,
                (string) $request->string('payment_method_id'),
            );

            $stripeSubscriptionId = (string) data_get($stripeSubscription, 'id', '');

            if ($stripeSubscriptionId === '') {
                throw new RuntimeException('Stripe subscription id missing in response.');
            }

            $status = (string) data_get($stripeSubscription, 'status', '');
            $mappedStatus = in_array($status, ['active', 'trialing'], true) ? 'active' : (in_array($status, ['past_due', 'unpaid', 'incomplete', 'incomplete_expired'], true) ? 'overdue' : 'canceled');

            $startsAt = now();
            $periodStart = data_get($stripeSubscription, 'current_period_start');

            if (is_numeric($periodStart)) {
                $startsAt = now()->setTimestamp((int) $periodStart);
            }

            $endsAt = null;
            $periodEnd = data_get($stripeSubscription, 'current_period_end');

            if (is_numeric($periodEnd)) {
                $endsAt = now()->setTimestamp((int) $periodEnd);
            }

            DB::transaction(function () use ($tenant, $plan, $stripeSubscriptionId, $mappedStatus, $startsAt, $endsAt): void {
                TenantSubscription::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'canceled',
                        'ends_at' => now(),
                    ]);

                TenantSubscription::query()->create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'stripe_subscription_id' => $stripeSubscriptionId,
                    'status' => $mappedStatus,
                    'ai_usage' => 0,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);
            });

            return response()->json([
                'message' => 'Subscription created successfully.',
                'stripe_subscription_id' => $stripeSubscriptionId,
                'status' => $mappedStatus,
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
