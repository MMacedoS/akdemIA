<?php

namespace App\Repositories\Entities\SystemAdmin;

use App\Models\SystemSetting;
use App\Repositories\Contracts\SystemAdmin\WorkoutxSettingsRepositoryContract;
use Illuminate\Support\Collection;

class WorkoutxSettingsRepository implements WorkoutxSettingsRepositoryContract
{
    public function values(): Collection
    {
        return SystemSetting::query()
            ->whereIn('key', [
                'workoutx.enabled',
                'workoutx.api_base_url',
                'workoutx.api_key',
                'workoutx.auth_mode',
                'workoutx.request_timeout',
                'workoutx.default_limit',
                'workoutx.allow_fallback',
            ])
            ->pluck('value', 'key');
    }

    public function update(array $payload): void
    {
        $this->upsert('workoutx.enabled', $payload['workoutx_enabled'] ?? '0', false);
        $this->upsert('workoutx.api_base_url', $payload['workoutx_api_base_url'] ?? null, false);
        $this->upsert('workoutx.api_key', $payload['workoutx_api_key'] ?? null, true);
        $this->upsert('workoutx.auth_mode', $payload['workoutx_auth_mode'] ?? 'header', false);
        $this->upsert('workoutx.request_timeout', isset($payload['workoutx_request_timeout']) ? (string) $payload['workoutx_request_timeout'] : null, false);
        $this->upsert('workoutx.default_limit', isset($payload['workoutx_default_limit']) ? (string) $payload['workoutx_default_limit'] : null, false);
        $this->upsert('workoutx.allow_fallback', $payload['workoutx_allow_fallback'] ?? '0', false);
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
                'domain' => 'workoutx',
                'key' => $key,
            ],
            [
                'value' => $normalized,
                'is_secret' => $isSecret,
            ]
        );
    }
}
