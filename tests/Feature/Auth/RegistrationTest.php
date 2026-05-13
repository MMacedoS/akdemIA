<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms_of_use' => '1',
            'privacy_policy' => '1',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'profile_type' => null,
            'terms_version' => config('legal.terms.version'),
            'privacy_policy_version' => config('legal.privacy_policy.version'),
        ]);

        $userId = (int) \App\Models\User::query()->where('email', 'test@example.com')->value('id');

        $this->assertDatabaseMissing('tenant_trainee', [
            'trainee_user_id' => $userId,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_users_must_accept_legal_documents_to_register()
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['terms_of_use', 'privacy_policy']);
        $this->assertGuest();
    }
}
