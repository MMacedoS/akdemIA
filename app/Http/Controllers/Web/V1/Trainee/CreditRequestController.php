<?php

namespace App\Http\Controllers\Web\V1\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Credits\CreditRequest;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Services\Billing\MercadoPagoService;
use App\Services\Billing\PaymentConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CreditRequestController extends Controller
{
    public function __construct(
        private readonly PaymentConfigService $paymentConfigService,
        private readonly MercadoPagoService $mercadoPagoService
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


        $creditsRequested = (int) $payload['credits_requested'];
        $externalReference = 'credits-trainee-' . Str::uuid();

        $payment = $this->mercadoPagoService->createPixPayment([
            'amount' => $creditsRequested,
            'email' => $user->email,
            'external_reference' => $externalReference,
        ]);

        CreditRequest::query()->create([
            'requester_user_id' => $user->id,

            'target_user_id' => $user->id,

            'tenant_id' => $tenant?->id,

            'credits_requested' => $creditsRequested,

            // mantém temporariamente
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

            'note' => isset($payload['note'])
                ? trim((string) $payload['note'])
                : null,
        ]);

        return redirect()->route('trainee.credits.index')
            ->with('status', 'Solicitacao de credito criada com sucesso. Utilize o QR Code Pix para pagamento.');
    }

    private function resolveTrainee(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isTrainee(), 403, 'Acesso permitido apenas para trainee.');

        return $user;
    }
}
