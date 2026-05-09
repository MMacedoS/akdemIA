<?php

namespace Tests\Unit\Services\Billing;

use App\Services\Billing\MercadoPagoService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoServiceTest extends TestCase
{
    public function test_create_pix_payment_posts_order_payload_and_normalizes_response(): void
    {
        config()->set('services.payment.api_token', 'test-token');
        config()->set('services.payment.api_base_url', 'https://api.mercadopago.com');

        Http::fake([
            'https://api.mercadopago.com/v1/orders' => Http::response([
                'id' => 'ORD-123',
                'external_reference' => 'credits-trainee-123',
                'status' => 'action_required',
                'status_detail' => 'waiting_transfer',
                'transactions' => [
                    'payments' => [[
                        'id' => 'PAY-123',
                        'reference_id' => 'REF-123',
                        'status' => 'action_required',
                        'status_detail' => 'waiting_transfer',
                        'payment_method' => [
                            'id' => 'pix',
                            'type' => 'bank_transfer',
                            'ticket_url' => 'https://example.test/ticket',
                            'qr_code' => 'pix-code',
                            'qr_code_base64' => 'base64-image',
                        ],
                    ]],
                ],
            ], 201),
        ]);

        $payload = app(MercadoPagoService::class)->createPixPayment([
            'amount' => 20,
            'email' => 'buyer@example.com',
            'external_reference' => 'credits-trainee-123',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.mercadopago.com/v1/orders'
                && $request['type'] === 'online'
                && $request['total_amount'] === '20.00'
                && $request['external_reference'] === 'credits-trainee-123'
                && $request['processing_mode'] === 'automatic'
                && $request['transactions']['payments'][0]['payment_method']['id'] === 'pix'
                && $request['transactions']['payments'][0]['payment_method']['type'] === 'bank_transfer'
                && $request['payer']['email'] === 'buyer@example.com'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request->hasHeader('X-Idempotency-Key');
        });

        $this->assertSame('ORD-123', $payload['order_id']);
        $this->assertSame('PAY-123', $payload['payment_id']);
        $this->assertSame('https://example.test/ticket', $payload['ticket_url']);
        $this->assertSame('pix-code', $payload['qr_code']);
        $this->assertSame('base64-image', $payload['qr_code_base64']);
    }
}
