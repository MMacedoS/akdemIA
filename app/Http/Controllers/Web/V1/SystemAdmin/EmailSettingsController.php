<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SystemAdmin\EmailSettingsRepositoryContract;
use App\Support\FormPatterns;
use App\Services\System\SystemAdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailSettingsController extends Controller
{
    public function __construct(
        private readonly EmailSettingsRepositoryContract $emailSettingsRepository,
        private readonly SystemAdminAuditLogger $auditLogger,
    ) {}

    public function edit(): View
    {
        return view('web.v1.system_admin.settings.email', [
            'settings' => $this->emailSettingsRepository->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $before = $this->emailSettingsRepository->values()->all();

        $validated = $request->validate([
            'mail_mailer' => ['nullable', 'string', 'max:40'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'max:30', 'in:tls,ssl,starttls,smtp,smtps,TLS,SSL,STARTTLS,SMTP,SMTPS'],
            'mail_from_address' => FormPatterns::looseEmail(false),
            'mail_from_name' => ['nullable', 'string', 'max:120'],
        ]);

        $validated['mail_from_address'] = FormPatterns::normalizeEmail($validated['mail_from_address'] ?? null);

        $this->emailSettingsRepository->update($validated);

        $this->auditLogger->log(
            $request->user()?->id,
            'email_settings',
            'updated',
            null,
            $before,
            $this->emailSettingsRepository->values()->all(),
        );

        return redirect()->route('system-admin.settings.email.edit')
            ->with('status', 'Configuracoes de email atualizadas.');
    }
}
