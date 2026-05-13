<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SystemAdmin\GoogleAuthSettingsRepositoryContract;
use App\Services\System\SystemAdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoogleAuthSettingsController extends Controller
{
    public function __construct(
        private readonly GoogleAuthSettingsRepositoryContract $googleAuthSettingsRepository,
        private readonly SystemAdminAuditLogger $auditLogger,
    ) {}

    public function edit(): View
    {
        return view('web.v1.system_admin.settings.google-auth', [
            'settings' => $this->googleAuthSettingsRepository->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $before = $this->googleAuthSettingsRepository->values()->all();

        $validated = $request->validate([
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
            'google_redirect_uri' => ['nullable', 'url', 'max:2000'],
        ]);

        $this->googleAuthSettingsRepository->update($validated);

        $this->auditLogger->log(
            $request->user()?->id,
            'google_auth_settings',
            'updated',
            null,
            $before,
            $this->googleAuthSettingsRepository->values()->all(),
        );

        return redirect()->route('system-admin.settings.google-auth.edit')
            ->with('status', 'Configuracoes do Google Auth atualizadas.');
    }
}
