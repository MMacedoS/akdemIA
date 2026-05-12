<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_trainee_users_are_redirected_to_trainee_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('trainee.dashboard'));
    }

    public function test_trainer_users_without_tenant_are_redirected_to_trainee_dashboard()
    {
        $user = User::factory()->create([
            'profile_type' => Role::TRAINER->value,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('trainee.dashboard'));
    }
}
