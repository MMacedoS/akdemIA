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
        if (! $this->hasValidWebhookSignature($request)) {
            return response()->json([
                'message' => 'Invalid webhook secret.',
            ], 401);
        }

        $resourceId = $this->resolveResourceId($request);

        if ($resourceId === null) {
            return response()->json([
                'received' => true,
                'processed' => false,
            ], 202);
        }

        $processed = $this->mercadoPagoService->syncNotification(
            $this->resolveResourceType($request),
            $resourceId,
            $request->input('data', []),
        );

        return response()->json([
            'received' => true,
            'processed' => $processed,
        ]);
    }

    private function hasValidWebhookSignature(Request $request): bool
    {
        $expectedSecret = trim($this->paymentConfigService->mercadoPagoWebhookSecret());

        if ($expectedSecret === '') {
            return false;
        }

        if ($this->hasValidLegacySharedSecret($request, $expectedSecret)) {
            return true;
        }

        return $this->hasValidMercadoPagoSignature($request, $expectedSecret);
    }

    private function hasValidLegacySharedSecret(Request $request, string $expectedSecret): bool
    {

        $receivedSecret = trim((string) ($request->query('secret')
            ?? $request->header('X-Webhook-Secret')
            ?? $request->header('X-MercadoPago-Webhook-Secret')
            ?? ''));

        return $receivedSecret !== '' && hash_equals($expectedSecret, $receivedSecret);
    }

    private function hasValidMercadoPagoSignature(Request $request, string $expectedSecret): bool
    {
        $signatureHeader = trim((string) $request->header('x-signature', ''));

        if ($signatureHeader === '') {
            return false;
        }

        $signatureParts = $this->parseSignatureHeader($signatureHeader);
        $timestamp = $signatureParts['ts'] ?? null;
        $hash = $signatureParts['v1'] ?? null;

        if (! is_string($timestamp) || $timestamp === '' || ! is_string($hash) || $hash === '') {
            return false;
        }

        $manifest = $this->buildMercadoPagoManifest($request, $timestamp);
        $expectedHash = hash_hmac('sha256', $manifest, $expectedSecret);

        return hash_equals($expectedHash, $hash);
    }

    /**
     * @return array<string, string>
     */
    private function parseSignatureHeader(string $signatureHeader): array
    {
        $parts = [];

        foreach (explode(',', $signatureHeader) as $fragment) {
            $keyValue = explode('=', trim($fragment), 2);

            if (count($keyValue) !== 2) {
                continue;
            }

            $key = trim($keyValue[0]);
            $value = trim($keyValue[1]);

            if ($key === '' || $value === '') {
                continue;
            }

            $parts[$key] = $value;
        }

        return $parts;
    }

    private function buildMercadoPagoManifest(Request $request, string $timestamp): string
    {
        $segments = [];
        $resourceId = $this->resolveSignatureResourceId($request);

        if ($resourceId !== null) {
            $segments[] = 'id:' . strtolower($resourceId) . ';';
        }

        $requestId = trim((string) $request->header('x-request-id', ''));

        if ($requestId !== '') {
            $segments[] = 'request-id:' . $requestId . ';';
        }

        $segments[] = 'ts:' . $timestamp . ';';

        return implode('', $segments);
    }

    private function resolveSignatureResourceId(Request $request): ?string
    {
        $candidates = [
            $request->query('data.id'),
            $request->query('id'),
        ];

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

    private function resolveResourceType(Request $request): string
    {
        $candidates = [
            $request->input('type'),
            $request->query('type'),
            $request->input('topic'),
            $request->query('topic'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $value = strtolower(trim((string) $candidate));

            if ($value !== '') {
                return $value;
            }
        }

        return 'order';
    }

    private function resolveResourceId(Request $request): ?string
    {
        $candidates = [
            $request->query('data.id'),
            $request->query('id'),
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
