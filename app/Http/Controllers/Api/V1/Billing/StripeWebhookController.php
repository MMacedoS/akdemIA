<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeBillingService $stripeBillingService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = (string) $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $this->stripeBillingService->isValidWebhookSignature($payload, $signature)) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response()->json([
                'message' => 'Invalid payload.',
            ], 400);
        }

        $eventType = (string) data_get($event, 'type', '');

        if (in_array($eventType, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            $subscriptionPayload = data_get($event, 'data.object');

            if (is_array($subscriptionPayload)) {
                $this->stripeBillingService->syncStripeSubscription($subscriptionPayload);
            }
        }

        return response()->json([
            'received' => true,
        ]);
    }
}
