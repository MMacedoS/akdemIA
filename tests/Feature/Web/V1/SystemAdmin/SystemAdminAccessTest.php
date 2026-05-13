<?php

namespace Tests\Feature\Web\V1\SystemAdmin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_access_dashboard_without_policy_onboarding(): void
    {
        $user = User::factory()->create([
            'profile_type' => null,
            'is_system_admin' => true,
            'terms_accepted_at' => null,
            'terms_version' => null,
            'privacy_policy_accepted_at' => null,
            'privacy_policy_version' => null,
        ]);

        $this->actingAs($user)
            ->get(route('system-admin.dashboard'))
            ->assertOk();
    }
}
