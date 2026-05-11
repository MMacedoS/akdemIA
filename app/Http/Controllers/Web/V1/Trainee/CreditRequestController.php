<?php

namespace App\Http\Controllers\Web\V1\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\User;
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
        $user = $this->resolveTrainee($request);
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            $tenant = null;
        }

        $requests = $this->creditRequestService->queryForRequester($user)
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
        $this->creditRequestService->createForRequester(
            $user,
            $tenant,
            $creditsRequested,
            isset($payload['note']) ? trim((string) $payload['note']) : null,
        );

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
