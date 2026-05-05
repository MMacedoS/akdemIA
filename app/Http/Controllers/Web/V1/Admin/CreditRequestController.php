<?php

namespace App\Http\Controllers\Web\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Credits\CreditRequest;
use App\Models\Tenant\Tenant;
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
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $requests = CreditRequest::query()
            ->where('requester_user_id', (int) $user?->id)
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('web.v1.admin.credits.index', [
            'requests' => $requests,
            'pixKey' => $this->paymentConfigService->pixKey(),
            'tenant' => $tenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        $payload = $request->validate([
            'credits_requested' => ['required', 'integer', 'min:1', 'max:10000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $pixKey = trim($this->paymentConfigService->pixKey());

        if ($pixKey === '') {
            return redirect()->route('admin.credits.index')
                ->withErrors(['credits' => 'PIX_KEY nao configurada no ambiente.']);
        }

        $creditsRequested = (int) $payload['credits_requested'];

        $creditRequest = CreditRequest::query()->create([
            'requester_user_id' => $user->id,
            'target_user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'credits_requested' => $creditsRequested,
            'pix_key' => $pixKey,
            'pix_payload' => $this->buildPixPayload($pixKey, $creditsRequested, $tenant->slug, $user->id),
            'qr_code_url' => '',
            'status' => 'pending',
            'note' => isset($payload['note']) ? trim((string) $payload['note']) : null,
        ]);

        $creditRequest->qr_code_url = $this->buildQrCodeUrl((string) $creditRequest->pix_payload);
        $creditRequest->save();

        return redirect()->route('admin.credits.index')
            ->with('status', 'Solicitacao de credito criada com sucesso. Utilize o QR Code PIX para pagamento.');
    }

    private function buildPixPayload(string $pixKey, int $creditsRequested, string $tenantSlug, int $userId): string
    {
        return sprintf(
            'PIX|CHAVE:%s|CREDITOS:%d|TENANT:%s|USER:%d|REF:%s',
            $pixKey,
            $creditsRequested,
            $tenantSlug,
            $userId,
            now()->format('YmdHis')
        );
    }

    private function buildQrCodeUrl(string $payload): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . urlencode($payload);
    }

    private function resolveTenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            abort(409, 'Tenant not identified.');
        }

        return $tenant;
    }
}
