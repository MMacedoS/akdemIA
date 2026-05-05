<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Notifications\TenantAccessCreatedNotification;
use App\Repositories\Contracts\SystemAdmin\TenantManagementRepositoryContract;
use App\Support\FormPatterns;
use App\Services\System\SystemAdminAuditLogger;
use App\Services\System\TenantCascadeDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TenantManagementController extends Controller
{
    private const DEFAULT_TENANT_PASSWORD = '@academai123';

    public function __construct(
        private readonly TenantManagementRepositoryContract $tenantRepository,
        private readonly SystemAdminAuditLogger $auditLogger,
        private readonly TenantCascadeDeletionService $tenantDeletionService,
    ) {}

    public function index(): View
    {
        return view('web.v1.system_admin.tenants.index', [
            'adminCandidates' => $this->tenantRepository->listAdminCandidates(),
            'tenants' => $this->tenantRepository->listRecent(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => FormPatterns::name(true, 120),
            'slug' => FormPatterns::slug(false, 120),
            'email' => FormPatterns::email(),
        ]);

        $tenant = $this->tenantRepository->create(
            (string) $validated['name'],
            isset($validated['slug']) ? (string) $validated['slug'] : null,
            (string) $validated['email'],
            self::DEFAULT_TENANT_PASSWORD,
        );

        $accessUser = $tenant->users()
            ->wherePivot('role', 'admin')
            ->latest('users.id')
            ->firstOrFail(['users.id', 'users.name', 'users.email']);

        $tenantUrl = $this->resolveTenantUrl((string) $tenant->slug);
        $loginUrl = route('login');

        $accessUser->notify(new TenantAccessCreatedNotification(
            (string) $tenant->name,
            $tenantUrl,
            $loginUrl,
            (string) $accessUser->email,
            self::DEFAULT_TENANT_PASSWORD,
        ));

        $this->auditLogger->log(
            $request->user()?->id,
            'tenant',
            'created',
            $tenant,
            null,
            $tenant->only(['name', 'slug', 'contact_email', 'contact_phone', 'document_number', 'is_active']),
            [
                'access_user_id' => $accessUser->id,
                'access_email' => $accessUser->email,
                'default_password_warning' => 'Troca obrigatoria no primeiro acesso.',
            ],
        );

        return redirect()->route('system-admin.tenants.index')
            ->with('status', 'Tenant criado com sucesso. Um e-mail com acesso inicial foi enviado e a senha deve ser trocada no primeiro login.');
    }

    public function edit(int $id): View
    {
        $tenant = $this->tenantRepository->findById($id);

        abort_if($tenant === null, 404);

        return view('web.v1.system_admin.tenants.edit', [
            'tenant' => $tenant,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tenant = $this->tenantRepository->findById($id);

        abort_if($tenant === null, 404);

        $validated = $request->validate([
            'name' => FormPatterns::name(true, 120),
            'slug' => FormPatterns::slug(true, 120),
            'contact_email' => FormPatterns::looseEmail(false, 190),
            'contact_phone' => FormPatterns::phone(),
            'document_number' => FormPatterns::document(),
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $before = $tenant->only(['name', 'slug', 'contact_email', 'contact_phone', 'document_number', 'notes', 'is_active']);
        $updatedTenant = $this->tenantRepository->update($tenant, [
            ...$validated,
            'contact_email' => FormPatterns::normalizeEmail($validated['contact_email'] ?? null),
            'contact_phone' => FormPatterns::formatPhone($validated['contact_phone'] ?? null),
            'document_number' => FormPatterns::formatDocument($validated['document_number'] ?? null),
            'is_active' => (bool) $request->boolean('is_active'),
        ]);

        $this->auditLogger->log(
            $request->user()?->id,
            'tenant',
            'updated',
            $updatedTenant,
            $before,
            $updatedTenant->only(['name', 'slug', 'contact_email', 'contact_phone', 'document_number', 'notes', 'is_active']),
        );

        return redirect()->route('system-admin.tenants.edit', $updatedTenant->id)
            ->with('status', 'Tenant atualizado com sucesso.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $tenant = $this->tenantRepository->findById($id);

        abort_if($tenant === null, 404);

        $before = $tenant->only(['id', 'name', 'slug', 'contact_email', 'contact_phone', 'document_number', 'notes', 'is_active']);
        $summary = $this->tenantDeletionService->delete($tenant);

        $this->auditLogger->log(
            $request->user()?->id,
            'tenant',
            'deleted',
            $tenant,
            $before,
            null,
            $summary,
        );

        return redirect()->route('system-admin.tenants.index')
            ->with('status', 'Tenant excluido com remocao em cascata dos dados associados.');
    }

    private function resolveTenantUrl(string $slug): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';
        $rootDomain = env('APP_LANDING_ROOT_DOMAIN');

        if (is_string($rootDomain) && trim($rootDomain) !== '') {
            return $scheme . '://' . Str::lower($slug) . '.' . trim($rootDomain);
        }

        return $appUrl;
    }
}
