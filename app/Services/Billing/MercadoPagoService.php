<?php

namespace App\Services\Billing;

use App\Models\Credits\CreditRequest;
use App\Services\Credits\CreditService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MercadoPagoService
{
    public function __construct(
        private readonly PaymentConfigService $paymentConfigService,
        private readonly CreditService $creditService,
    ) {}

    public function createPixPayment(array $data): array
    {
        $accessToken = trim($this->paymentConfigService->apiToken());

        if ($accessToken === '') {
            $accessToken = trim((string) config('services.mercadopago.token', ''));
        }

        if ($accessToken === '') {
            throw new RuntimeException('Mercado Pago access token is missing.');
        }

        $baseUrl = rtrim($this->paymentConfigService->apiBaseUrl(), '/');

        if ($baseUrl === '') {
            $baseUrl = 'https://api.mercadopago.com';
        }

        $response = Http::acceptJson()
            ->withHeaders([
                'X-Idempotency-Key' => (string) Str::uuid(),
            ])
            ->withToken($accessToken)
            ->post(
                $baseUrl . '/v1/orders',
                $this->buildPixOrderPayload($data)
            );

        if (! $response->successful()) {
            $message = (string) data_get($response->json(), 'message', $response->body());

            throw new RuntimeException($message);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid Mercado Pago response.');
        }

        return $this->normalizePixOrderResponse($payload);
    }

    public function syncOrderById(string $orderId): bool
    {
        $order = $this->getOrder($orderId);

        if (! $this->isPixPaymentApproved($order)) {
            return false;
        }

        $externalReference = trim((string) ($order['external_reference'] ?? ''));

        if ($externalReference === '') {
            return false;
        }

        return DB::transaction(function () use ($externalReference, $order): bool {
            $creditRequest = CreditRequest::query()
                ->lockForUpdate()
                ->where('payment_external_reference', $externalReference)
                ->first();

            if ($creditRequest === null) {
                return false;
            }

            $creditRequest->fill([
                'payment_provider_payment_id' => $this->stringOrNull($order['payment_id'] ?? null),
                'payment_ticket_url' => $this->stringOrNull($order['ticket_url'] ?? null),
                'payment_status' => $this->stringOrNull($order['payment_status'] ?? null),
                'payment_status_detail' => $this->stringOrNull($order['payment_status_detail'] ?? null),
                'payment_payload' => $order['raw'] ?? null,
                'pix_payload' => (string) ($order['qr_code'] ?? $creditRequest->pix_payload),
                'qr_code_url' => $this->buildQrCodeDataUri($order['qr_code_base64'] ?? null, $creditRequest->qr_code_url),
            ]);

            if ($creditRequest->status !== 'pending') {
                $creditRequest->save();

                return true;
            }

            $this->creditService->addCredits(
                $creditRequest->targetUser,
                (int) $creditRequest->credits_requested,
                null,
                'request_paid',
                'Credito liberado automaticamente apos confirmacao do pagamento Pix.',
                [
                    'credit_request_id' => $creditRequest->id,
                    'payment_external_reference' => $creditRequest->payment_external_reference,
                    'payment_provider_payment_id' => $creditRequest->payment_provider_payment_id,
                ],
                $creditRequest->tenant,
                $creditRequest,
            );

            $creditRequest->fill([
                'status' => 'approved',
                'reviewed_at' => now(),
            ]);
            $creditRequest->save();

            return true;
        }, 3);
    }

    public function getOrder(string $orderId): array
    {
        $accessToken = trim($this->paymentConfigService->apiToken());

        if ($accessToken === '') {
            $accessToken = trim((string) config('services.mercadopago.token', ''));
        }

        if ($accessToken === '') {
            throw new RuntimeException('Mercado Pago access token is missing.');
        }

        $baseUrl = rtrim($this->paymentConfigService->apiBaseUrl(), '/');

        if ($baseUrl === '') {
            $baseUrl = 'https://api.mercadopago.com';
        }

        $response = Http::acceptJson()
            ->withToken($accessToken)
            ->get($baseUrl . '/v1/orders/' . ltrim($orderId, '/'));

        if (! $response->successful()) {
            $message = (string) data_get($response->json(), 'message', $response->body());

            throw new RuntimeException($message);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid Mercado Pago order response.');
        }

        return $this->normalizePixOrderResponse($payload);
    }

    private function buildPixOrderPayload(array $data): array
    {
        $amount = number_format((float) ($data['amount'] ?? 0), 2, '.', '');

        if ((float) $amount <= 0) {
            throw new RuntimeException('Pix amount must be greater than zero.');
        }

        $email = trim((string) ($data['email'] ?? ''));

        if ($email === '') {
            throw new RuntimeException('Payer email is required.');
        }

        $externalReference = trim((string) ($data['external_reference'] ?? ''));

        if ($externalReference === '') {
            throw new RuntimeException('External reference is required.');
        }

        return [
            'type' => 'online',
            'total_amount' => $amount,
            'external_reference' => $externalReference,
            'processing_mode' => 'automatic',
            'transactions' => [
                'payments' => [[
                    'amount' => $amount,
                    'payment_method' => [
                        'id' => 'pix',
                        'type' => 'bank_transfer',
                    ],
                    'expiration_time' => (string) ($data['expiration_time'] ?? 'PT24H'),
                ]],
            ],
            'payer' => [
                'email' => $email,
            ],
        ];
    }

    private function normalizePixOrderResponse(array $payload): array
    {
        $payment = data_get($payload, 'transactions.payments.0', []);

        if (! is_array($payment)) {
            throw new RuntimeException('Invalid Mercado Pago payment payload.');
        }

        return [
            'order_id' => data_get($payload, 'id'),
            'external_reference' => data_get($payload, 'external_reference'),
            'status' => data_get($payload, 'status'),
            'status_detail' => data_get($payload, 'status_detail'),
            'payment_id' => data_get($payment, 'id'),
            'payment_reference_id' => data_get($payment, 'reference_id'),
            'payment_status' => data_get($payment, 'status'),
            'payment_status_detail' => data_get($payment, 'status_detail'),
            'ticket_url' => data_get($payment, 'payment_method.ticket_url'),
            'qr_code' => data_get($payment, 'payment_method.qr_code'),
            'qr_code_base64' => data_get($payment, 'payment_method.qr_code_base64'),
            'raw' => $payload,
        ];
    }

    private function isPixPaymentApproved(array $order): bool
    {
        return (string) ($order['payment_status'] ?? '') === 'approved';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function buildQrCodeDataUri(mixed $base64, string $fallback): string
    {
        if (! is_string($base64) || trim($base64) === '') {
            return $fallback;
        }

        return 'data:image/jpeg;base64,' . trim($base64);
    }
}
