<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Notifications\AccountDeletionRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_drop_account_page_can_be_rendered(): void
    {
        $this->get(route('drop-account.create'))
            ->assertOk();
    }

    public function test_account_deletion_request_sends_confirmation_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'delete-me@example.com',
        ]);

        $this->post(route('drop-account.store'), [
            'email' => 'delete-me@example.com',
        ])->assertRedirect(route('drop-account.create'));

        Notification::assertSentTo($user, AccountDeletionRequestNotification::class);
    }

    public function test_account_can_be_deleted_from_signed_confirmation_link(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_path' => 'avatars/test-avatar.jpg',
        ]);

        Storage::disk('public')->put('avatars/test-avatar.jpg', 'avatar');

        $confirmationUrl = URL::temporarySignedRoute(
            'drop-account.confirm',
            now()->addMinutes(60),
            [
                'user' => $user->id,
                'hash' => sha1((string) $user->email),
            ],
        );

        $this->post($confirmationUrl)
            ->assertRedirect(route('drop-account.create'));

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
        $this->assertFalse(Storage::disk('public')->exists('avatars/test-avatar.jpg'));
    }

    public function test_invalid_hash_does_not_delete_account(): void
    {
        $user = User::factory()->create();

        $confirmationUrl = URL::temporarySignedRoute(
            'drop-account.confirm',
            now()->addMinutes(60),
            [
                'user' => $user->id,
                'hash' => sha1('invalid@example.com'),
            ],
        );

        $this->post($confirmationUrl)->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }
}
