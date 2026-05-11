<?php

namespace App\Http\Controllers\Web\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Services\Credits\CreditRequestService;
use App\Services\Billing\PaymentConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditRequestController extends Controller
{
    public function __construct(
        private readonly PaymentConfigService $paymentConfigService,
        private readonly CreditRequestService $creditRequestService,
    ) {}

    public function index(Request $request): View
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $requests = $this->creditRequestService->queryForRequester($user)
            ->paginate(10)
            ->withQueryString();

        return view('web.v1.admin.credits.index', [
            'requests' => $requests,
            'pixKey' => $this->paymentConfigService->pixKey(),
            'tenant' => $tenant,
        ]);
    }

    public function store(Request $request)
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

        $creditsRequested = (int) $payload['credits_requested'];
        $this->creditRequestService->createForRequester(
            $user,
            $tenant,
            $creditsRequested,
            isset($payload['note']) ? trim((string) $payload['note']) : null,
        );

        return redirect()
            ->route('admin.credits.index')
            ->with(
                'status',
                'Solicitação de crédito criada com sucesso. Utilize o QR Code Pix para pagamento.'
            );
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
