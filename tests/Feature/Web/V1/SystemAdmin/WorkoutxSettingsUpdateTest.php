<?php

namespace Tests\Feature\Web\V1\SystemAdmin;

use App\Enums\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutxSettingsUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_update_workoutx_and_vector_store_settings(): void
    {
        $user = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
            'is_system_admin' => true,
        ]);

        $response = $this->actingAs($user)
            ->from(route('system-admin.settings.workoutx.edit'))
            ->put(route('system-admin.settings.workoutx.update'), [
                'workoutx_enabled' => '1',
                'workoutx_api_base_url' => 'https://api.workoutxapp.com/v1',
                'workoutx_api_key' => 'new-secret',
                'workoutx_auth_mode' => 'header',
                'workoutx_request_timeout' => '25',
                'workoutx_default_limit' => '10',
                'workoutx_sync_page_delay_seconds' => '180',
                'workoutx_allow_fallback' => '1',
                'vector_store_enabled' => '1',
                'vector_store_scope' => 'tenant',
                'vector_store_catalog_type' => 'runtime_catalog',
                'vector_store_name_prefix' => 'runtime-prefix',
                'vector_store_existing_id' => 'vs_existing_runtime_123',
                'vector_store_existing_name' => 'runtime-existing-name',
                'vector_store_file_purpose' => 'assistants',
                'vector_store_max_search_results' => '20',
                'vector_store_minimum_candidates' => '11',
                'vector_store_storage_path' => 'ai/runtime-workout-catalog.jsonl',
            ]);

        $response->assertRedirect(route('system-admin.settings.workoutx.edit'));
        $response->assertSessionHas('status', 'Configuracoes da WorkoutX e Vector Store atualizadas.');

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'workoutx',
            'key' => 'workoutx.enabled',
            'value' => '1',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.enabled',
            'value' => '1',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.scope',
            'value' => 'tenant',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.catalog_type',
            'value' => 'runtime_catalog',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.name_prefix',
            'value' => 'runtime-prefix',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.existing_id',
            'value' => 'vs_existing_runtime_123',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.existing_name',
            'value' => 'runtime-existing-name',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.file_purpose',
            'value' => 'assistants',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.max_search_results',
            'value' => '20',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'workoutx',
            'key' => 'openai.vector_store.minimum_candidates',
            'value' => '11',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'workoutx',
            'key' => 'internal_catalog.vector_store_storage_path',
            'value' => 'ai/runtime-workout-catalog.jsonl',
        ]);

        $secret = SystemSetting::query()->where('key', 'workoutx.api_key')->first();

        $this->assertNotNull($secret);
        $this->assertTrue((bool) $secret?->is_secret);
    }
}
