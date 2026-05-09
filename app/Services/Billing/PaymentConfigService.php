<?php

namespace App\Services\Billing;

class PaymentConfigService
{
    public function providerName(): string
    {
        return (string) config('services.payment.provider_name', 'mercadopago');
    }

    public function apiBaseUrl(): string
    {
        return (string) config('services.payment.api_base_url', '');
    }

    public function apiToken(): string
    {
        return (string) config('services.payment.api_token', '');
    }

    public function mercadoPagoBaseUrl(): string
    {
        return (string) config('services.payment.api_base_url', config('services.mercadopago.base_url', 'https://api.mercadopago.com'));
    }

    public function mercadoPagoAccessToken(): string
    {
        return (string) config('services.payment.api_token', config('services.mercadopago.token', ''));
    }

    public function mercadoPagoWebhookSecret(): string
    {
        return (string) config('services.mercadopago.webhook_secret', '');
    }

    public function pixKey(): string
    {
        return (string) config('services.payment.pix_key', config('services.pix.key', ''));
    }
}
