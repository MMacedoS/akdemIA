<?php

namespace App\Repositories\Entities\SystemAdmin;

use App\Models\SystemSetting;
use App\Repositories\Contracts\SystemAdmin\EmailSettingsRepositoryContract;
use App\Support\FormPatterns;
use Illuminate\Support\Collection;

class EmailSettingsRepository implements EmailSettingsRepositoryContract
{
    public function values(): Collection
    {
        return SystemSetting::query()
            ->whereIn('key', [
                'mail.mailer',
                'mail.host',
                'mail.port',
                'mail.username',
                'mail.password',
                'mail.encryption',
                'mail.from_address',
                'mail.from_name',
            ])
            ->pluck('value', 'key');
    }

    public function update(array $payload): void
    {
        $this->upsert('mail.mailer', $payload['mail_mailer'] ?? null, false);
        $this->upsert('mail.host', $payload['mail_host'] ?? null, false);
        $this->upsert('mail.port', isset($payload['mail_port']) ? (string) $payload['mail_port'] : null, false);
        $this->upsert('mail.username', $payload['mail_username'] ?? null, false);
        $this->upsert('mail.password', $payload['mail_password'] ?? null, true);
        $this->upsert('mail.encryption', $this->normalizeMailEncryption($payload['mail_encryption'] ?? null), false);
        $this->upsert('mail.from_address', FormPatterns::normalizeEmail($payload['mail_from_address'] ?? null), false);
        $this->upsert('mail.from_name', $payload['mail_from_name'] ?? null, false);
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
                'domain' => 'mail',
                'key' => $key,
            ],
            [
                'value' => $normalized,
                'is_secret' => $isSecret,
            ]
        );
    }

    private function normalizeMailEncryption(?string $value): ?string
    {
        $normalized = is_string($value) ? mb_strtolower(trim($value)) : null;

        return $normalized !== '' ? $normalized : null;
    }
}
