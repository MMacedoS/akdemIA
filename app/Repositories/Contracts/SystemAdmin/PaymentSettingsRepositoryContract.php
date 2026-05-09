<?php

namespace App\Repositories\Contracts\SystemAdmin;

use Illuminate\Support\Collection;

interface PaymentSettingsRepositoryContract
{
    public function values(): Collection;

    public function update(
        ?string $apiBaseUrl,
        ?string $apiToken,
        ?string $pixKey,
        ?string $mercadoPagoWebhookSecret,
    ): void;
}
