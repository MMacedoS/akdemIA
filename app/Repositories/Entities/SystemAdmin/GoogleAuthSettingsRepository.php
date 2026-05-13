<?php

namespace App\Repositories\Entities\SystemAdmin;

use App\Models\SystemSetting;
use App\Repositories\Contracts\SystemAdmin\GoogleAuthSettingsRepositoryContract;
use Illuminate\Support\Collection;

class GoogleAuthSettingsRepository implements GoogleAuthSettingsRepositoryContract
{
    public function values(): Collection
    {
        return SystemSetting::query()
            ->whereIn('key', [
                'google.client_id',
                'google.client_secret',
                'google.redirect_uri',
            ])
            ->pluck('value', 'key');
    }

    public function update(array $payload): void
    {
        $this->upsert('google.client_id', $payload['google_client_id'] ?? null, false);
        $this->upsert('google.client_secret', $payload['google_client_secret'] ?? null, true);
        $this->upsert('google.redirect_uri', $payload['google_redirect_uri'] ?? null, false);
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
                'domain' => 'google',
                'key' => $key,
            ],
            [
                'value' => $normalized,
                'is_secret' => $isSecret,
            ]
        );
    }
}
