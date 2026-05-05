<?php

namespace App\Repositories\Contracts\SystemAdmin;

use Illuminate\Support\Collection;

interface PaymentSettingsRepositoryContract
{
    /**
     * @return Collection<string, string|null>
     */
    public function values(): Collection;

    public function update(
        ?string $providerName,
        ?string $apiBaseUrl,
        ?string $apiToken,
        ?string $pixKey,
        ?string $stripeSecret,
        ?string $stripeWebhookSecret,
    ): void;
}
