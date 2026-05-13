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

    public function test_apply_maps_vector_store_settings_to_runtime_config(): void
    {
        SystemSetting::query()->create([
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.enabled',
            'value' => '0',
            'is_secret' => false,
        ]);

        SystemSetting::query()->create([
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.scope',
            'value' => 'tenant',
            'is_secret' => false,
        ]);

        SystemSetting::query()->create([
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.catalog_type',
            'value' => 'runtime_catalog',
            'is_secret' => false,
        ]);

        SystemSetting::query()->create([
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.name_prefix',
            'value' => 'runtime-prefix',
            'is_secret' => false,
        ]);

        SystemSetting::query()->create([
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.existing_id',
            'value' => 'vs_existing_runtime_123',
            'is_secret' => false,
        ]);

        SystemSetting::query()->create([
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.existing_name',
            'value' => 'runtime-existing-name',
            'is_secret' => false,
        ]);

        SystemSetting::query()->create([
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.file_purpose',
            'value' => 'vision',
            'is_secret' => false,
        ]);

        SystemSetting::query()->create([
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.max_search_results',
            'value' => '18',
            'is_secret' => false,
        ]);

        SystemSetting::query()->create([
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.minimum_candidates',
            'value' => '9',
            'is_secret' => false,
        ]);

        SystemSetting::query()->create([
            'domain' => 'workoutx',
            'key' => 'internal_catalog.vector_store_storage_path',
            'value' => 'ai/runtime-catalog.jsonl',
            'is_secret' => false,
        ]);

        config()->set('services.openai.vector_store.enabled', true);
        config()->set('services.openai.vector_store.scope', 'global');
        config()->set('services.openai.vector_store.catalog_type', 'workout_exercises');
        config()->set('services.openai.vector_store.name_prefix', 'akdemia-workouts');
        config()->set('services.openai.vector_store.existing_id', '');
        config()->set('services.openai.vector_store.existing_name', '');
        config()->set('services.openai.vector_store.file_purpose', 'assistants');
        config()->set('services.openai.vector_store.max_search_results', 24);
        config()->set('services.openai.vector_store.minimum_candidates', 12);
        config()->set('services.internal_catalog.vector_store_storage_path', 'ai/openai-workout-catalog.jsonl');

        app(SystemSettingsRuntimeService::class)->apply();

        $this->assertFalse(config('services.openai.vector_store.enabled'));
        $this->assertSame('tenant', config('services.openai.vector_store.scope'));
        $this->assertSame('runtime_catalog', config('services.openai.vector_store.catalog_type'));
        $this->assertSame('runtime-prefix', config('services.openai.vector_store.name_prefix'));
        $this->assertSame('vs_existing_runtime_123', config('services.openai.vector_store.existing_id'));
        $this->assertSame('runtime-existing-name', config('services.openai.vector_store.existing_name'));
        $this->assertSame('vision', config('services.openai.vector_store.file_purpose'));
        $this->assertSame(18, config('services.openai.vector_store.max_search_results'));
        $this->assertSame(9, config('services.openai.vector_store.minimum_candidates'));
        $this->assertSame('ai/runtime-catalog.jsonl', config('services.internal_catalog.vector_store_storage_path'));
    }

    public function test_apply_maps_google_auth_settings_to_runtime_config(): void
    {
        SystemSetting::query()->create([
            'domain' => 'google',
            'key' => 'google.client_id',
            'value' => 'runtime-client-id',
            'is_secret' => false,
        ]);

        SystemSetting::query()->create([
            'domain' => 'google',
            'key' => 'google.client_secret',
            'value' => 'runtime-client-secret',
            'is_secret' => true,
        ]);

        SystemSetting::query()->create([
            'domain' => 'google',
            'key' => 'google.redirect_uri',
            'value' => 'https://runtime.exemplo.com/auth/google/callback',
            'is_secret' => false,
        ]);

        config()->set('services.google.client_id', 'env-client-id');
        config()->set('services.google.client_secret', 'env-client-secret');
        config()->set('services.google.redirect', 'https://env.exemplo.com/auth/google/callback');

        app(SystemSettingsRuntimeService::class)->apply();

        $this->assertSame('runtime-client-id', config('services.google.client_id'));
        $this->assertSame('runtime-client-secret', config('services.google.client_secret'));
        $this->assertSame('https://runtime.exemplo.com/auth/google/callback', config('services.google.redirect'));
    }
}
