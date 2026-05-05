<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SystemAdmin\TraineeManagementRepositoryContract;
use App\Support\FormPatterns;
use App\Services\System\SystemAdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TraineeManagementController extends Controller
{
    public function __construct(
        private readonly TraineeManagementRepositoryContract $traineeRepository,
        private readonly SystemAdminAuditLogger $auditLogger,
    ) {}

    public function index(): View
    {
        return view('web.v1.system_admin.trainees.index', [
            'trainees' => $this->traineeRepository->listRecent(),
            'tenants' => $this->traineeRepository->listTenantOptions(),
            'links' => $this->traineeRepository->listRecentLinks(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => FormPatterns::name(true, 120),
            'email' => FormPatterns::email(null, 'users', 'email', true),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $trainer = $this->traineeRepository->create(
            (string) $validated['name'],
            (string) FormPatterns::normalizeEmail((string) $validated['email']),
            (string) $validated['password'],
        );

        $this->auditLogger->log(
            $request->user()?->id,
            'trainer',
            'created',
            $trainer,
            null,
            $trainer->only(['name', 'email', 'profile_type', 'is_active']),
        );

        return redirect()->route('system-admin.trainees.index')
            ->with('status', 'Trainer criado com sucesso.');
    }

    public function linkTenant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'trainee_user_id' => ['required', 'integer', 'exists:users,id'],
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->traineeRepository->linkToTenant(
            (int) $validated['trainee_user_id'],
            (int) $validated['tenant_id'],
            $request->user()?->id,
            isset($validated['note']) ? (string) $validated['note'] : null,
        );

        $this->auditLogger->log(
            $request->user()?->id,
            'trainee_tenant_link',
            'linked',
            null,
            null,
            [
                'trainee_user_id' => (int) $validated['trainee_user_id'],
                'tenant_id' => (int) $validated['tenant_id'],
                'note' => $validated['note'] ?? null,
            ],
        );

        return redirect()->route('system-admin.trainees.index')
            ->with('status', 'Vinculo de trainer com tenant salvo com sucesso.');
    }
}
