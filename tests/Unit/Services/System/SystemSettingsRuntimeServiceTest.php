<?php

namespace Tests\Unit\Services\System;

use App\Models\SystemSetting;
use App\Services\System\SystemSettingsRuntimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingsRuntimeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_maps_tls_encryption_to_smtp_scheme(): void
    {
        SystemSetting::query()->create([
            'domain' => 'mail',
            'key' => 'mail.encryption',
            'value' => 'tls',
            'is_secret' => false,
        ]);

        config()->set('mail.mailers.smtp.scheme', null);

        app(SystemSettingsRuntimeService::class)->apply();

        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
    }

    public function test_apply_maps_ssl_encryption_to_smtps_scheme(): void
    {
        SystemSetting::query()->create([
            'domain' => 'mail',
            'key' => 'mail.encryption',
            'value' => 'ssl',
            'is_secret' => false,
        ]);

        config()->set('mail.mailers.smtp.scheme', null);

        app(SystemSettingsRuntimeService::class)->apply();

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }
}
