<?php

namespace App\Transformers\Credits;

use App\Models\Credits\CreditRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CreditRequestTransformer
{
    public function transform(CreditRequest $creditRequest): array
    {
        return [
            'id' => (int) $creditRequest->id,
            'requester_user_id' => (int) $creditRequest->requester_user_id,
            'target_user_id' => (int) $creditRequest->target_user_id,
            'tenant_id' => $creditRequest->tenant_id === null ? null : (int) $creditRequest->tenant_id,
            'credits_requested' => (int) $creditRequest->credits_requested,
            'status' => (string) $creditRequest->status,
            'payment_status' => $creditRequest->payment_status,
            'payment_status_detail' => $creditRequest->payment_status_detail,
            'payment_external_reference' => $creditRequest->payment_external_reference,
            'payment_provider_payment_id' => $creditRequest->payment_provider_payment_id,
            'payment_ticket_url' => $creditRequest->payment_ticket_url,
            'pix_key' => $creditRequest->pix_key,
            'pix_payload' => $creditRequest->pix_payload,
            'qr_code_url' => $creditRequest->qr_code_url,
            'reviewed_by_user_id' => $creditRequest->reviewed_by_user_id === null ? null : (int) $creditRequest->reviewed_by_user_id,
            'reviewed_at' => $creditRequest->reviewed_at?->toIso8601String(),
            'note' => $creditRequest->note,
            'created_at' => $creditRequest->created_at?->toIso8601String(),
            'updated_at' => $creditRequest->updated_at?->toIso8601String(),
        ];
    }

    public function transformPaginator(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => collect($paginator->items())
                ->map(fn(CreditRequest $creditRequest) => $this->transform($creditRequest))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
