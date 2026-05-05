<?php

namespace App\Services\Billing;

use App\Models\Tenant\Plan;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class StripeBillingService
{
    public function __construct(
        private readonly PaymentConfigService $paymentConfigService,
    ) {}

    public function createCustomer(Tenant $tenant, string $email, string $name): string
    {
        if (is_string($tenant->stripe_id) && $tenant->stripe_id !== '') {
            return $tenant->stripe_id;
        }

        $response = $this->stripeRequest('customers', [
            'email' => $email,
            'name' => $name,
            'metadata[tenant_id]' => (string) $tenant->id,
            'metadata[tenant_slug]' => $tenant->slug,
        ]);

        $stripeCustomerId = (string) data_get($response, 'id', '');

        if ($stripeCustomerId === '') {
            throw new RuntimeException('Stripe customer id missing in response.');
        }

        $tenant->stripe_id = $stripeCustomerId;
        $tenant->save();

        return $stripeCustomerId;
    }

    public function createSubscription(Tenant $tenant, Plan $plan, string $paymentMethodId): array
    {
        $stripePriceId = (string) data_get($plan->features, 'stripe_price_id', '');

        if ($stripePriceId === '') {
            throw new RuntimeException('Plan is missing features.stripe_price_id.');
        }

        if (! is_string($tenant->stripe_id) || $tenant->stripe_id === '') {
            throw new RuntimeException('Tenant stripe_id is missing.');
        }

        $this->stripeRequest('payment_methods/' . $paymentMethodId . '/attach', [
            'customer' => $tenant->stripe_id,
        ]);

        $this->stripeRequest('customers/' . $tenant->stripe_id, [
            'invoice_settings[default_payment_method]' => $paymentMethodId,
        ]);

        return $this->stripeRequest('subscriptions', [
            'customer' => $tenant->stripe_id,
            'items[0][price]' => $stripePriceId,
            'default_payment_method' => $paymentMethodId,
            'expand[0]' => 'latest_invoice.payment_intent',
        ]);
    }

    public function upgradeSubscriptionPlan(Tenant $tenant, Plan $plan): array
    {
        $stripePriceId = (string) data_get($plan->features, 'stripe_price_id', '');

        if ($stripePriceId === '') {
            throw new RuntimeException('Plan is missing features.stripe_price_id.');
        }

        $currentSubscription = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('stripe_subscription_id')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'overdue' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->first();

        if ($currentSubscription === null || ! is_string($currentSubscription->stripe_subscription_id) || $currentSubscription->stripe_subscription_id === '') {
            throw new RuntimeException('No Stripe subscription found for tenant.');
        }

        $stripeSubscriptionId = $currentSubscription->stripe_subscription_id;

        $stripeSubscription = $this->stripeGet('subscriptions/' . $stripeSubscriptionId);
        $itemId = (string) data_get($stripeSubscription, 'items.data.0.id', '');

        if ($itemId === '') {
            throw new RuntimeException('Stripe subscription item id not found.');
        }

        $updatedSubscription = $this->stripeRequest('subscriptions/' . $stripeSubscriptionId, [
            'items[0][id]' => $itemId,
            'items[0][price]' => $stripePriceId,
            'proration_behavior' => 'create_prorations',
        ]);

        $this->syncStripeSubscription($updatedSubscription);

        return $updatedSubscription;
    }

    public function syncStripeSubscription(array $subscriptionPayload): void
    {
        $stripeCustomerId = (string) data_get($subscriptionPayload, 'customer', '');
        $stripeSubscriptionId = (string) data_get($subscriptionPayload, 'id', '');

        if ($stripeCustomerId === '' || $stripeSubscriptionId === '') {
            return;
        }

        $tenant = Tenant::query()->where('stripe_id', $stripeCustomerId)->first();

        if ($tenant === null) {
            return;
        }

        $priceId = (string) data_get($subscriptionPayload, 'items.data.0.price.id', '');

        $plan = Plan::query()
            ->where('features->stripe_price_id', $priceId)
            ->first();

        if ($plan === null) {
            $plan = Plan::query()->orderBy('id')->first();
        }

        if ($plan === null) {
            return;
        }

        $status = $this->mapStripeStatus((string) data_get($subscriptionPayload, 'status', ''));
        $startsAt = $this->timestampToCarbon(data_get($subscriptionPayload, 'current_period_start')) ?? now();
        $endsAt = $this->resolveEndsAt($subscriptionPayload, $status);

        DB::transaction(function () use ($tenant, $plan, $stripeSubscriptionId, $status, $startsAt, $endsAt): void {
            if ($status === 'active') {
                TenantSubscription::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'active')
                    ->where('stripe_subscription_id', '!=', $stripeSubscriptionId)
                    ->update([
                        'status' => 'canceled',
                        'ends_at' => now(),
                    ]);
            }

            TenantSubscription::query()->updateOrCreate(
                ['stripe_subscription_id' => $stripeSubscriptionId],
                [
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'status' => $status,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ],
            );
        });
    }

    public function isValidWebhookSignature(string $payload, ?string $signatureHeader): bool
    {
        $webhookSecret = $this->paymentConfigService->stripeWebhookSecret();

        if ($webhookSecret === '' || ! is_string($signatureHeader) || $signatureHeader === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't' && is_string($value)) {
                $timestamp = $value;
            }

            if ($key === 'v1' && is_string($value)) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $webhookSecret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function stripeRequest(string $path, array $payload): array
    {
        $secretKey = $this->paymentConfigService->stripeSecret();

        if ($secretKey === '') {
            throw new RuntimeException('Stripe secret key is missing.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->withToken($secretKey)
            ->post('https://api.stripe.com/v1/' . ltrim($path, '/'), $payload);

        if (! $response->successful()) {
            $message = (string) data_get($response->json(), 'error.message', 'Stripe request failed.');
            throw new RuntimeException($message);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Invalid Stripe response.');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function stripeGet(string $path): array
    {
        $secretKey = $this->paymentConfigService->stripeSecret();

        if ($secretKey === '') {
            throw new RuntimeException('Stripe secret key is missing.');
        }

        $response = Http::acceptJson()
            ->withToken($secretKey)
            ->get('https://api.stripe.com/v1/' . ltrim($path, '/'));

        if (! $response->successful()) {
            $message = (string) data_get($response->json(), 'error.message', 'Stripe request failed.');
            throw new RuntimeException($message);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Invalid Stripe response.');
        }

        return $data;
    }

    private function mapStripeStatus(string $stripeStatus): string
    {
        return match (Str::lower($stripeStatus)) {
            'active', 'trialing' => 'active',
            'past_due', 'unpaid', 'incomplete', 'incomplete_expired' => 'overdue',
            default => 'canceled',
        };
    }

    private function resolveEndsAt(array $subscriptionPayload, string $status): ?Carbon
    {
        $canceledAt = $this->timestampToCarbon(data_get($subscriptionPayload, 'canceled_at'));

        if ($canceledAt !== null) {
            return $canceledAt;
        }

        $endedAt = $this->timestampToCarbon(data_get($subscriptionPayload, 'ended_at'));

        if ($endedAt !== null) {
            return $endedAt;
        }

        if ($status === 'canceled') {
            return now();
        }

        return $this->timestampToCarbon(data_get($subscriptionPayload, 'current_period_end'));
    }

    private function timestampToCarbon(mixed $value): ?Carbon
    {
        if (! is_numeric($value)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $value);
    }
}
