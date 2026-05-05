<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Models\Credits\CreditRequest;
use App\Models\User;
use App\Services\Credits\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function __construct(
        private readonly CreditService $creditService,
    ) {}

    public function grant(Request $request): RedirectResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            abort(401, 'Unauthenticated.');
        }

        $payload = $request->validate([
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'credits' => ['required', 'integer', 'min:1', 'max:50000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $targetUser = User::query()->findOrFail((int) $payload['target_user_id']);
        $credits = (int) $payload['credits'];

        $this->creditService->addCredits(
            $targetUser,
            $credits,
            $actor,
            'admin_grant',
            'Credito concedido manualmente por admin do sistema.',
            [
                'note' => isset($payload['note']) ? trim((string) $payload['note']) : null,
            ],
        );

        return redirect()->route('system-admin.credits.index')
            ->with('status', 'Creditos adicionados com sucesso para o usuario selecionado.');
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            abort(401, 'Unauthenticated.');
        }

        $creditRequest = CreditRequest::query()->where('status', 'pending')->findOrFail($id);

        $this->creditService->addCredits(
            $creditRequest->targetUser,
            (int) $creditRequest->credits_requested,
            $actor,
            'request_approved',
            'Credito concedido por aprovacao da solicitacao.',
            [
                'credit_request_id' => $creditRequest->id,
            ],
            $creditRequest->tenant,
            $creditRequest,
        );

        $creditRequest->fill([
            'status' => 'approved',
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
        ]);
        $creditRequest->save();

        return redirect()->route('system-admin.credits.index')
            ->with('status', 'Solicitacao aprovada e creditos adicionados ao usuario.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            abort(401, 'Unauthenticated.');
        }

        $payload = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $creditRequest = CreditRequest::query()->where('status', 'pending')->findOrFail($id);

        $creditRequest->fill([
            'status' => 'rejected',
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
            'note' => isset($payload['reason']) && trim((string) $payload['reason']) !== ''
                ? trim((string) $payload['reason'])
                : $creditRequest->note,
        ]);
        $creditRequest->save();

        return redirect()->route('system-admin.credits.index')
            ->with('status', 'Solicitacao rejeitada com sucesso.');
    }
}
