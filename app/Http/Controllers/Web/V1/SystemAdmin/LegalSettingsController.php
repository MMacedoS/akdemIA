<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Repositories\Contracts\SystemAdmin\LegalSettingsRepositoryContract;
use App\Services\System\SystemAdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalSettingsController extends Controller
{
    public function __construct(
        private readonly LegalSettingsRepositoryContract $legalSettingsRepository,
        private readonly SystemAdminAuditLogger $auditLogger,
    ) {}

    public function edit(): View
    {
        return view('web.v1.system_admin.settings.legal', [
            'documents' => $this->legalSettingsRepository->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $before = $this->legalSettingsRepository->values()->all();

        $validated = $request->validate([
            'terms_title' => ['required', 'string', 'max:150'],
            'terms_version' => ['required', 'string', 'max:50'],
            'terms_effective_date' => ['required', 'date'],
            'terms_content_html' => ['required', 'string'],
            'privacy_title' => ['required', 'string', 'max:150'],
            'privacy_version' => ['required', 'string', 'max:50'],
            'privacy_effective_date' => ['required', 'date'],
            'privacy_content_html' => ['required', 'string'],
        ]);

        $this->legalSettingsRepository->update([
            LegalDocument::TYPE_TERMS => [
                'title' => $validated['terms_title'],
                'slug' => 'termos-de-uso',
                'version' => $validated['terms_version'],
                'effective_date' => $validated['terms_effective_date'],
                'content_html' => $validated['terms_content_html'],
            ],
            LegalDocument::TYPE_PRIVACY_POLICY => [
                'title' => $validated['privacy_title'],
                'slug' => 'politica-de-privacidade',
                'version' => $validated['privacy_version'],
                'effective_date' => $validated['privacy_effective_date'],
                'content_html' => $validated['privacy_content_html'],
            ],
        ]);

        $this->auditLogger->log(
            $request->user()?->id,
            'legal_settings',
            'updated',
            null,
            $before,
            $this->legalSettingsRepository->values()->all(),
        );

        return redirect()->route('system-admin.settings.legal.edit')
            ->with('status', 'Documentos legais atualizados.');
    }
}
