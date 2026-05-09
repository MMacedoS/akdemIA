<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\PaymentConfigService;
use App\Services\Billing\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(
        private readonly MercadoPagoService $mercadoPagoService,
        private readonly PaymentConfigService $paymentConfigService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->hasValidSharedSecret($request)) {
            return response()->json([
                'message' => 'Invalid webhook secret.',
            ], 401);
        }

        $orderId = $this->resolveOrderId($request);

        if ($orderId === null) {
            return response()->json([
                'received' => true,
                'processed' => false,
            ], 202);
        }

        $processed = $this->mercadoPagoService->syncOrderById($orderId);

        return response()->json([
            'received' => true,
            'processed' => $processed,
        ]);
    }

    private function hasValidSharedSecret(Request $request): bool
    {
        $expectedSecret = trim($this->paymentConfigService->mercadoPagoWebhookSecret());

        if ($expectedSecret === '') {
            return false;
        }

        $receivedSecret = trim((string) ($request->query('secret')
            ?? $request->header('X-Webhook-Secret')
            ?? $request->header('X-MercadoPago-Webhook-Secret')
            ?? ''));

        return $receivedSecret !== '' && hash_equals($expectedSecret, $receivedSecret);
    }

    private function resolveOrderId(Request $request): ?string
    {
        $candidates = [
            $request->input('data.id'),
            $request->input('id'),
            data_get($request->all(), 'resource.id'),
        ];

        $resource = $request->input('resource');

        if (is_string($resource) && $resource !== '') {
            $candidates[] = basename($resource);
        }

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $value = trim((string) $candidate);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
