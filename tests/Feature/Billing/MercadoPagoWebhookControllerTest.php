<?php

namespace Tests\Feature\Billing;

use App\Models\Credits\CreditRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_requests_without_valid_shared_secret(): void
    {
        config()->set('services.mercadopago.webhook_secret', 'shared-secret');

        $response = $this->postJson(route('api.billing.mercadopago.webhook'), [
            'data' => [
                'id' => 'ORD-123',
            ],
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'message' => 'Invalid webhook secret.',
            ]);
    }

    public function test_webhook_approves_credit_request_and_adds_credits_when_payment_is_approved(): void
    {
        config()->set('services.payment.api_token', 'test-token');
        config()->set('services.payment.api_base_url', 'https://api.mercadopago.com');
        config()->set('services.mercadopago.webhook_secret', 'shared-secret');

        $user = User::factory()->create([
            'profile_type' => 'trainee',
            'credits_balance' => 0,
        ]);

        $creditRequest = CreditRequest::query()->create([
            'requester_user_id' => $user->id,
            'target_user_id' => $user->id,
            'tenant_id' => null,
            'credits_requested' => 25,
            'pix_key' => 'mercadopago',
            'pix_payload' => 'pix-code',
            'qr_code_url' => 'data:image/jpeg;base64,old-image',
            'payment_external_reference' => 'credits-trainee-123',
            'payment_provider_payment_id' => null,
            'payment_ticket_url' => null,
            'payment_status' => 'action_required',
            'payment_status_detail' => 'waiting_transfer',
            'payment_payload' => null,
            'status' => 'pending',
            'note' => null,
        ]);

        Http::fake([
            'https://api.mercadopago.com/v1/orders/ORD-123' => Http::response([
                'id' => 'ORD-123',
                'external_reference' => 'credits-trainee-123',
                'status' => 'processed',
                'status_detail' => 'accredited',
                'transactions' => [
                    'payments' => [[
                        'id' => 'PAY-123',
                        'reference_id' => 'REF-123',
                        'status' => 'approved',
                        'status_detail' => 'accredited',
                        'payment_method' => [
                            'id' => 'pix',
                            'type' => 'bank_transfer',
                            'ticket_url' => 'https://example.test/ticket',
                            'qr_code' => 'new-pix-code',
                            'qr_code_base64' => 'new-base64-image',
                        ],
                    ]],
                ],
            ]),
        ]);

        $response = $this->postJson(route('api.billing.mercadopago.webhook', ['secret' => 'shared-secret']), [
            'data' => [
                'id' => 'ORD-123',
            ],
            'type' => 'order',
        ]);

        $response->assertOk()
            ->assertJson([
                'received' => true,
                'processed' => true,
            ]);

        $creditRequest->refresh();
        $user->refresh();

        $this->assertSame('approved', $creditRequest->status);
        $this->assertSame('approved', $creditRequest->payment_status);
        $this->assertSame('PAY-123', $creditRequest->payment_provider_payment_id);
        $this->assertSame('https://example.test/ticket', $creditRequest->payment_ticket_url);
        $this->assertSame('new-pix-code', $creditRequest->pix_payload);
        $this->assertSame('data:image/jpeg;base64,new-base64-image', $creditRequest->qr_code_url);
        $this->assertSame(25, $user->credits_balance);
        $this->assertDatabaseHas('credit_transactions', [
            'credit_request_id' => $creditRequest->id,
            'type' => 'request_paid',
            'amount' => 25,
        ]);
    }
}
