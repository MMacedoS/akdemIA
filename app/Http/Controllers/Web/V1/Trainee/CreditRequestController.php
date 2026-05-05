<?php

namespace App\Http\Controllers\Web\V1\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Credits\CreditRequest;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Services\Billing\PaymentConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditRequestController extends Controller
{
    public function __construct(
        private readonly PaymentConfigService $paymentConfigService,
    ) {}

    public function index(Request $request): View
    {
        $user = $this->resolveTrainee($request);
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            $tenant = null;
        }

        $requests = CreditRequest::query()
            ->where('requester_user_id', $user->id)
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('web.v1.trainee.credits.index', [
            'requests' => $requests,
            'pixKey' => $this->paymentConfigService->pixKey(),
            'tenant' => $tenant,
            'trainee' => $user,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->resolveTrainee($request);
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            $tenant = null;
        }

        $payload = $request->validate([
            'credits_requested' => ['required', 'integer', 'min:1', 'max:10000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $pixKey = trim($this->paymentConfigService->pixKey());

        if ($pixKey === '') {
            return redirect()->route('trainee.credits.index')
                ->withErrors(['credits' => 'PIX_KEY nao configurada no ambiente.']);
        }

        $creditsRequested = (int) $payload['credits_requested'];
        $contextLabel = $tenant?->slug ?? 'personal';

        $creditRequest = CreditRequest::query()->create([
            'requester_user_id' => $user->id,
            'target_user_id' => $user->id,
            'tenant_id' => $tenant?->id,
            'credits_requested' => $creditsRequested,
            'pix_key' => $pixKey,
            'pix_payload' => $this->buildPixPayload($pixKey, $creditsRequested, $contextLabel, $user->id),
            'qr_code_url' => '',
            'status' => 'pending',
            'note' => isset($payload['note']) ? trim((string) $payload['note']) : null,
        ]);

        $creditRequest->qr_code_url = $this->buildQrCodeUrl((string) $creditRequest->pix_payload);
        $creditRequest->save();

        return redirect()->route('trainee.credits.index')
            ->with('status', 'Solicitacao de credito criada com sucesso. Utilize o QR Code PIX para pagamento.');
    }

    private function buildPixPayload(string $pixKey, int $creditsRequested, string $contextLabel, int $userId): string
    {
        return sprintf(
            'PIX|CHAVE:%s|CREDITOS:%d|CONTEXTO:%s|USER:%d|REF:%s',
            $pixKey,
            $creditsRequested,
            $contextLabel,
            $userId,
            now()->format('YmdHis')
        );
    }

    private function buildQrCodeUrl(string $payload): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . urlencode($payload);
    }

    private function resolveTrainee(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isTrainee(), 403, 'Acesso permitido apenas para trainee.');

        return $user;
    }
}
