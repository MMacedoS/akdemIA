<?php

namespace App\Services\Credits;

use App\Enums\Role;
use App\Models\Credits\CreditRequest;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Services\Billing\MercadoPagoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CreditRequestService
{
    public function __construct(
        private readonly MercadoPagoService $mercadoPagoService,
    ) {}

    public function queryForRequester(User $user): Builder
    {
        return CreditRequest::query()
            ->where('requester_user_id', $user->id)
            ->orderByDesc('id');
    }

    public function findOwnedRequestOrFail(User $user, int $id): CreditRequest
    {
        return $this->queryForRequester($user)->findOrFail($id);
    }

    public function createForRequester(User $user, ?Tenant $tenant, int $creditsRequested, ?string $note = null): CreditRequest
    {
        $externalReference = 'credits-' . $this->resolveReferencePrefix($user, $tenant) . '-' . Str::uuid();

        $payment = $this->mercadoPagoService->createPixPayment([
            'amount' => $creditsRequested,
            'email' => $user->email,
            'external_reference' => $externalReference,
        ]);

        return CreditRequest::query()->create([
            'requester_user_id' => $user->id,
            'target_user_id' => $user->id,
            'tenant_id' => $tenant?->id,
            'credits_requested' => $creditsRequested,
            'pix_key' => 'mercadopago',
            'pix_payload' => (string) ($payment['qr_code'] ?? ''),
            'qr_code_url' => isset($payment['qr_code_base64']) && is_string($payment['qr_code_base64']) && $payment['qr_code_base64'] !== ''
                ? 'data:image/jpeg;base64,' . $payment['qr_code_base64']
                : '',
            'payment_external_reference' => $externalReference,
            'payment_provider_payment_id' => is_scalar($payment['payment_id'] ?? null)
                ? (string) $payment['payment_id']
                : null,
            'payment_ticket_url' => is_string($payment['ticket_url'] ?? null)
                ? $payment['ticket_url']
                : null,
            'payment_status' => is_string($payment['payment_status'] ?? null)
                ? $payment['payment_status']
                : null,
            'payment_status_detail' => is_string($payment['payment_status_detail'] ?? null)
                ? $payment['payment_status_detail']
                : null,
            'payment_payload' => is_array($payment['raw'] ?? null)
                ? $payment['raw']
                : null,
            'status' => 'pending',
            'note' => $note,
        ]);
    }

    private function resolveReferencePrefix(User $user, ?Tenant $tenant): string
    {
        if ($user->isTrainee()) {
            return 'trainee';
        }

        if ($tenant instanceof Tenant && $user->getRole($tenant) === Role::ADMIN) {
            return 'admin';
        }

        return 'user';
    }
}
