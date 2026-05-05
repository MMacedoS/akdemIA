<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SystemAdmin\PaymentSettingsRepositoryContract;
use App\Services\System\SystemAdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentSettingsController extends Controller
{
    public function __construct(
        private readonly PaymentSettingsRepositoryContract $paymentSettingsRepository,
        private readonly SystemAdminAuditLogger $auditLogger,
    ) {}

    public function edit(): View
    {
        return view('web.v1.system_admin.settings.payment', [
            'settings' => $this->paymentSettingsRepository->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $before = $this->paymentSettingsRepository->values()->all();

        $validated = $request->validate([
            'payment_provider_name' => ['nullable', 'string', 'max:120'],
            'payment_api_base_url' => ['nullable', 'url', 'max:2000'],
            'payment_api_token' => ['nullable', 'string', 'max:2000'],
            'payment_pix_key' => ['nullable', 'string', 'max:255'],
            'payment_stripe_secret' => ['nullable', 'string', 'max:255'],
            'payment_stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $this->paymentSettingsRepository->update(
            $validated['payment_provider_name'] ?? null,
            $validated['payment_api_base_url'] ?? null,
            $validated['payment_api_token'] ?? null,
            $validated['payment_pix_key'] ?? null,
            $validated['payment_stripe_secret'] ?? null,
            $validated['payment_stripe_webhook_secret'] ?? null,
        );

        $this->auditLogger->log(
            $request->user()?->id,
            'payment_settings',
            'updated',
            null,
            $before,
            $this->paymentSettingsRepository->values()->all(),
        );

        return redirect()->route('system-admin.settings.payment.edit')
            ->with('status', 'Configuracoes de pagamento atualizadas.');
    }
}
