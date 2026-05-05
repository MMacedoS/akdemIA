<?php

namespace App\Services\Billing;

class PaymentConfigService
{
    public function providerName(): string
    {
        return (string) config('services.payment.provider_name', 'stripe');
    }

    public function apiBaseUrl(): string
    {
        return (string) config('services.payment.api_base_url', '');
    }

    public function apiToken(): string
    {
        return (string) config('services.payment.api_token', '');
    }

    public function pixKey(): string
    {
        return (string) config('services.payment.pix_key', config('services.pix.key', ''));
    }

    public function stripeSecret(): string
    {
        return (string) config('services.payment.stripe_secret', config('services.stripe.secret', ''));
    }

    public function stripeWebhookSecret(): string
    {
        return (string) config('services.payment.stripe_webhook_secret', config('services.stripe.webhook_secret', ''));
    }
}
