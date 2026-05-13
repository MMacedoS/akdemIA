<?php

namespace Tests\Feature\Web\V1\SystemAdmin;

use App\Enums\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleAuthSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_update_google_auth_settings(): void
    {
        $user = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
            'is_system_admin' => true,
        ]);

        $response = $this->actingAs($user)
            ->put(route('system-admin.settings.google-auth.update'), [
                'google_client_id' => 'google-client-id.apps.googleusercontent.com',
                'google_client_secret' => 'google-client-secret',
                'google_redirect_uri' => 'https://app.exemplo.com/auth/google/callback',
            ]);

        $response->assertRedirect(route('system-admin.settings.google-auth.edit'));

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'google',
            'key' => 'google.client_id',
            'value' => 'google-client-id.apps.googleusercontent.com',
            'is_secret' => false,
        ]);

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'google',
            'key' => 'google.client_secret',
            'value' => 'google-client-secret',
            'is_secret' => true,
        ]);

        $this->assertDatabaseHas('system_settings', [
            'domain' => 'google',
            'key' => 'google.redirect_uri',
            'value' => 'https://app.exemplo.com/auth/google/callback',
            'is_secret' => false,
        ]);
    }

    public function test_google_auth_secret_is_preserved_when_blank(): void
    {
        $user = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
            'is_system_admin' => true,
        ]);

        SystemSetting::query()->create([
            'domain' => 'google',
            'key' => 'google.client_secret',
            'value' => 'segredo-atual',
            'is_secret' => true,
        ]);

        $this->actingAs($user)
            ->put(route('system-admin.settings.google-auth.update'), [
                'google_client_id' => 'novo-client-id.apps.googleusercontent.com',
                'google_client_secret' => '',
                'google_redirect_uri' => 'https://app.exemplo.com/auth/google/callback',
            ])
            ->assertRedirect(route('system-admin.settings.google-auth.edit'));

        $this->assertSame(
            'segredo-atual',
            SystemSetting::query()->where('key', 'google.client_secret')->value('value')
        );
    }
}
