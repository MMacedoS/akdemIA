<?php

namespace Tests\Feature\Web\V1\SystemAdmin;

use App\Enums\Role;
use App\Models\Credits\CreditRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_mark_pix_request_as_paid_and_approve_it(): void
    {
        $systemAdmin = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
            'is_system_admin' => true,
        ]);

        $targetUser = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'credits_balance' => 0,
        ]);

        $creditRequest = CreditRequest::query()->create([
            'requester_user_id' => $targetUser->id,
            'target_user_id' => $targetUser->id,
            'tenant_id' => null,
            'credits_requested' => 25,
            'pix_key' => 'mercadopago',
            'pix_payload' => 'pix-code',
            'qr_code_url' => 'data:image/jpeg;base64,old-image',
            'payment_external_reference' => 'credits-trainee-manual-123',
            'payment_provider_payment_id' => null,
            'payment_ticket_url' => null,
            'payment_status' => 'pending',
            'payment_status_detail' => 'waiting_transfer',
            'payment_payload' => null,
            'status' => 'pending',
            'note' => null,
        ]);

        $response = $this->actingAs($systemAdmin)
            ->post(route('system-admin.requests.approve', $creditRequest->id), [
                'mark_as_paid' => '1',
            ]);

        $response->assertRedirect(route('system-admin.credits.index'));

        $creditRequest->refresh();
        $targetUser->refresh();

        $this->assertSame('approved', $creditRequest->status);
        $this->assertSame('approved', $creditRequest->payment_status);
        $this->assertSame('manual_system_admin_confirmation', $creditRequest->payment_status_detail);
        $this->assertSame($systemAdmin->id, $creditRequest->reviewed_by_user_id);
        $this->assertSame(25, $targetUser->credits_balance);

        $this->assertDatabaseHas('credit_transactions', [
            'credit_request_id' => $creditRequest->id,
            'user_id' => $targetUser->id,
            'actor_user_id' => $systemAdmin->id,
            'type' => 'request_approved',
            'amount' => 25,
        ]);
    }

    public function test_system_admin_cannot_approve_pending_pix_request_without_manual_confirmation(): void
    {
        $systemAdmin = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
            'is_system_admin' => true,
        ]);

        $targetUser = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'credits_balance' => 0,
        ]);

        $creditRequest = CreditRequest::query()->create([
            'requester_user_id' => $targetUser->id,
            'target_user_id' => $targetUser->id,
            'tenant_id' => null,
            'credits_requested' => 10,
            'pix_key' => 'mercadopago',
            'pix_payload' => 'pix-code',
            'qr_code_url' => 'data:image/jpeg;base64,old-image',
            'payment_external_reference' => 'credits-trainee-manual-456',
            'payment_provider_payment_id' => null,
            'payment_ticket_url' => null,
            'payment_status' => 'pending',
            'payment_status_detail' => 'waiting_transfer',
            'payment_payload' => null,
            'status' => 'pending',
            'note' => null,
        ]);

        $response = $this->actingAs($systemAdmin)
            ->post(route('system-admin.requests.approve', $creditRequest->id));

        $response->assertRedirect(route('system-admin.credits.index'));
        $response->assertSessionHas('status', 'A solicitacao ainda nao teve o pagamento Pix confirmado pelo Mercado Pago.');

        $creditRequest->refresh();
        $targetUser->refresh();

        $this->assertSame('pending', $creditRequest->status);
        $this->assertSame('pending', $creditRequest->payment_status);
        $this->assertSame(0, $targetUser->credits_balance);

        $this->assertDatabaseMissing('credit_transactions', [
            'credit_request_id' => $creditRequest->id,
        ]);
    }
}
