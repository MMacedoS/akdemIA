<?php

namespace App\Repositories\Entities\SystemAdmin;

use App\Models\SystemSetting;
use App\Repositories\Contracts\SystemAdmin\PaymentSettingsRepositoryContract;
use Illuminate\Support\Collection;

class PaymentSettingsRepository implements PaymentSettingsRepositoryContract
{
    public function values(): Collection
    {
        return SystemSetting::query()
            ->whereIn('key', [
                'payment.provider_name',
                'payment.api_base_url',
                'payment.api_token',
                'payment.pix_key',
                'payment.stripe_secret',
                'payment.stripe_webhook_secret',
            ])
            ->pluck('value', 'key');
    }

    public function update(
        ?string $providerName,
        ?string $apiBaseUrl,
        ?string $apiToken,
        ?string $pixKey,
        ?string $stripeSecret,
        ?string $stripeWebhookSecret,
    ): void {
        $this->upsert('payment.provider_name', $providerName, false);
        $this->upsert('payment.api_base_url', $apiBaseUrl, false);
        $this->upsert('payment.api_token', $apiToken, true);
        $this->upsert('payment.pix_key', $pixKey, true);
        $this->upsert('payment.stripe_secret', $stripeSecret, true);
        $this->upsert('payment.stripe_webhook_secret', $stripeWebhookSecret, true);
    }

    private function upsert(string $key, ?string $value, bool $isSecret): void
    {
        $normalized = is_string($value) ? trim($value) : null;

        if ($isSecret && ($normalized === null || $normalized === '')) {
            return;
        }

        if ($normalized === '') {
            $normalized = null;
        }

        SystemSetting::query()->updateOrCreate(
            [
                'domain' => 'payment',
                'key' => $key,
            ],
            [
                'value' => $normalized,
                'is_secret' => $isSecret,
            ]
        );
    }
}
