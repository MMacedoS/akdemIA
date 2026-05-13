<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Services\Tenant\PlatformTenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_profile_user_can_select_trainer_and_is_attached_to_platform_tenant(): void
    {
        $user = User::factory()->create([
            'profile_type' => null,
        ]);

        $response = $this->actingAs($user)->post(route('onboarding.profile.update'), [
            'profile_type' => Role::TRAINER->value,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'profile_type' => Role::TRAINER->value,
        ]);

        $platformTenantId = \App\Models\Tenant\Tenant::query()
            ->where('slug', PlatformTenantService::PLATFORM_TENANT_SLUG)
            ->value('id');

        $this->assertDatabaseHas('tenant_trainee', [
            'tenant_id' => $platformTenantId,
            'trainee_user_id' => $user->id,
        ]);
    }

    public function test_pending_profile_user_can_select_student(): void
    {
        $user = User::factory()->create([
            'profile_type' => null,
        ]);

        $response = $this->actingAs($user)->post(route('onboarding.profile.update'), [
            'profile_type' => Role::STUDENT->value,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'profile_type' => Role::STUDENT->value,
        ]);
        $this->assertDatabaseMissing('tenant_trainee', [
            'trainee_user_id' => $user->id,
        ]);
    }

    public function test_pending_profile_user_can_select_tenant_admin(): void
    {
        $user = User::factory()->create([
            'profile_type' => null,
        ]);

        $response = $this->actingAs($user)->post(route('onboarding.profile.update'), [
            'profile_type' => Role::ADMIN->value,
        ]);

        $response->assertRedirect(route('onboarding.contractor'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'profile_type' => Role::ADMIN->value,
        ]);
    }

    public function test_tenant_admin_can_create_first_tenant_from_onboarding(): void
    {
        $user = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
        ]);

        $response = $this->actingAs($user)->post(route('onboarding.contractor.store'), [
            'name' => 'Studio Norte',
            'slug' => 'studio-norte',
            'contact_email' => 'contato@studio.test',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('tenants', [
            'name' => 'Studio Norte',
            'slug' => 'studio-norte',
            'contact_email' => 'contato@studio.test',
        ]);

        $tenantId = (int) \App\Models\Tenant\Tenant::query()
            ->where('slug', 'studio-norte')
            ->value('id');

        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'role' => Role::ADMIN->value,
        ]);
        $this->assertSame($tenantId, session('tenant_id'));
    }
}
