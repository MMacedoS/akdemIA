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

    public function test_apply_maps_workout_rules_to_runtime_config(): void
    {
        SystemSetting::query()->create([
            'domain' => 'workout',
            'key' => 'workout.generate_cost',
            'value' => '7',
            'is_secret' => false,
        ]);

        SystemSetting::query()->create([
            'domain' => 'workout',
            'key' => 'workout.reuse_cost',
            'value' => '4',
            'is_secret' => false,
        ]);

        SystemSetting::query()->create([
            'domain' => 'workout',
            'key' => 'workout.reactivate_cost',
            'value' => '2',
            'is_secret' => false,
        ]);

        SystemSetting::query()->create([
            'domain' => 'workout',
            'key' => 'workout.active_days',
            'value' => '45',
            'is_secret' => false,
        ]);

        app(SystemSettingsRuntimeService::class)->apply();

        $this->assertSame(7, config('workouts.credits.generate'));
        $this->assertSame(4, config('workouts.credits.reuse'));
        $this->assertSame(2, config('workouts.credits.reactivate'));
        $this->assertSame(45, config('workouts.active_days'));
    }

    public function test_apply_maps_workoutx_sync_delay_to_runtime_config(): void
    {
        SystemSetting::query()->create([
            'domain' => 'workoutx',
            'key' => 'workoutx.sync_page_delay_seconds',
            'value' => '180',
            'is_secret' => false,
        ]);

        config()->set('services.workoutx.sync_page_delay_seconds', 120);

        app(SystemSettingsRuntimeService::class)->apply();

        $this->assertSame(180, config('services.workoutx.sync_page_delay_seconds'));
    }
}
