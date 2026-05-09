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

    public function test_webhook_accepts_official_signature_for_order_notifications_with_embedded_payload(): void
    {
        config()->set('services.mercadopago.webhook_secret', 'shared-secret');

        $user = User::factory()->create([
            'profile_type' => 'trainee',
            'credits_balance' => 0,
        ]);

        $creditRequest = CreditRequest::query()->create([
            'requester_user_id' => $user->id,
            'target_user_id' => $user->id,
            'tenant_id' => null,
            'credits_requested' => 5,
            'pix_key' => 'mercadopago',
            'pix_payload' => 'pix-code',
            'qr_code_url' => 'data:image/jpeg;base64,old-image',
            'payment_external_reference' => 'credits-trainee-5908b436-0a75-480a-81a0-a60667370203',
            'payment_provider_payment_id' => null,
            'payment_ticket_url' => null,
            'payment_status' => 'pending',
            'payment_status_detail' => 'waiting_transfer',
            'payment_payload' => null,
            'status' => 'pending',
            'note' => null,
        ]);

        $timestamp = '1704908010';
        $requestId = 'request-order-123';
        $signature = hash_hmac(
            'sha256',
            'id:ord01kr79ysqn0pxr0j9zrka04g9c;request-id:' . $requestId . ';ts:' . $timestamp . ';',
            'shared-secret',
        );

        $response = $this->withHeaders([
            'x-signature' => 'ts=' . $timestamp . ',v1=' . $signature,
            'x-request-id' => $requestId,
        ])->postJson(route('api.billing.mercadopago.webhook', [
            'data.id' => 'ORD01KR79YSQN0PXR0J9ZRKA04G9C',
            'type' => 'order',
        ]), [
            'action' => 'order.processed',
            'api_version' => 'v1',
            'application_id' => '8056256056997101',
            'data' => [
                'currency_id' => 'BRL',
                'external_reference' => 'credits-trainee-5908b436-0a75-480a-81a0-a60667370203',
                'id' => 'ORD01KR79YSQN0PXR0J9ZRKA04G9C',
                'status' => 'processed',
                'status_detail' => 'accredited',
                'total_amount' => '5.00',
                'total_paid_amount' => '0',
                'transactions' => [
                    'payments' => [[
                        'amount' => '5.00',
                        'id' => 'PAY01KR79YSR1QP3C1CRC97YQTHZG',
                        'paid_amount' => '0',
                        'payment_method' => [
                            'e2e_id' => 'PIXE18236120202605092123s004a3c1dcb',
                            'id' => 'pix',
                            'installments' => 0,
                            'type' => 'bank_transfer',
                        ],
                        'reference' => [
                            'id' => '000bk5umhm',
                        ],
                        'status' => 'processed',
                        'status_detail' => 'accredited',
                    ]],
                ],
                'type' => 'online',
                'version' => 3,
            ],
            'date_created' => '2026-05-09T21:23:38.19553084Z',
            'live_mode' => true,
            'type' => 'order',
            'user_id' => '185011228',
        ]);

        $response->assertOk()
            ->assertJson([
                'received' => true,
                'processed' => true,
            ]);

        $creditRequest->refresh();
        $user->refresh();

        $this->assertSame('approved', $creditRequest->status);
        $this->assertSame('processed', $creditRequest->payment_status);
        $this->assertSame('PAY01KR79YSR1QP3C1CRC97YQTHZG', $creditRequest->payment_provider_payment_id);
        $this->assertSame(5, $user->credits_balance);
    }

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

    public function test_webhook_processes_payment_updated_notifications(): void
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
            'credits_requested' => 5,
            'pix_key' => 'mercadopago',
            'pix_payload' => 'pix-code',
            'qr_code_url' => 'data:image/jpeg;base64,old-image',
            'payment_external_reference' => 'credits-trainee-5908b436-0a75-480a-81a0-a60667370203',
            'payment_provider_payment_id' => null,
            'payment_ticket_url' => null,
            'payment_status' => 'pending',
            'payment_status_detail' => 'waiting_transfer',
            'payment_payload' => null,
            'status' => 'pending',
            'note' => null,
        ]);

        Http::fake([
            'https://api.mercadopago.com/v1/payments/157785512799' => Http::response([
                'id' => '157785512799',
                'external_reference' => 'credits-trainee-5908b436-0a75-480a-81a0-a60667370203',
                'status' => 'approved',
                'status_detail' => 'accredited',
                'point_of_interaction' => [
                    'transaction_data' => [
                        'ticket_url' => 'https://example.test/payment-ticket',
                        'qr_code' => 'updated-pix-code',
                        'qr_code_base64' => 'updated-base64-image',
                    ],
                ],
            ]),
        ]);

        $timestamp = '1704908010';
        $requestId = 'request-payment-123';
        $signature = hash_hmac(
            'sha256',
            'id:157785512799;request-id:' . $requestId . ';ts:' . $timestamp . ';',
            'shared-secret',
        );

        $response = $this->withHeaders([
            'x-signature' => 'ts=' . $timestamp . ',v1=' . $signature,
            'x-request-id' => $requestId,
        ])->postJson(route('api.billing.mercadopago.webhook', [
            'data.id' => '157785512799',
            'type' => 'payment',
        ]), [
            'action' => 'payment.updated',
            'api_version' => 'v1',
            'data' => [
                'id' => '157785512799',
            ],
            'date_created' => '2026-05-09T21:23:08Z',
            'id' => 131903408402,
            'live_mode' => true,
            'type' => 'payment',
            'user_id' => '185011228',
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
        $this->assertSame('157785512799', $creditRequest->payment_provider_payment_id);
        $this->assertSame('https://example.test/payment-ticket', $creditRequest->payment_ticket_url);
        $this->assertSame('updated-pix-code', $creditRequest->pix_payload);
        $this->assertSame(5, $user->credits_balance);
    }
}
