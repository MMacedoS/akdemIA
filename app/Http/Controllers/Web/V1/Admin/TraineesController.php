<?php

namespace App\Http\Controllers\Web\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Repositories\Contracts\Tenant\TenantTraineeRepositoryContract;
use App\Support\FormPatterns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TraineesController extends Controller
{
    public function __construct(
        private readonly TenantTraineeRepositoryContract $repository,
    ) {}

    public function index(Request $request): View
    {
        $tenant = $this->resolveTenant($request);
        $search = trim((string) $request->query('q', ''));

        return view('web.v1.admin.trainees.index', [
            'trainees' => $this->repository->paginateForTenant($tenant, $search),
            'search' => $search,
            'metrics' => $this->repository->metricsForTenant($tenant),
        ]);
    }

    public function create(): View
    {
        return view('web.v1.admin.trainees.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);

        $payload = $request->validate([
            'name' => FormPatterns::name(),
            'email' => FormPatterns::email(),
            'password' => ['required', 'string', 'min:8'],
        ]);

        $this->repository->createForTenant($tenant, $payload, $request->user()?->id);

        return redirect()->route('admin.trainees.index')
            ->with('status', 'Trainee criado e vinculado ao tenant com sucesso.');
    }

    public function show(Request $request, int $id): View
    {
        $tenant = $this->resolveTenant($request);

        return view('web.v1.admin.trainees.show', [
            'trainee' => $this->repository->findInTenant($tenant, $id),
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        $tenant = $this->resolveTenant($request);

        return view('web.v1.admin.trainees.edit', [
            'trainee' => $this->repository->findInTenant($tenant, $id),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);
        $trainee = $this->repository->findInTenant($tenant, $id);

        $payload = $request->validate([
            'name' => FormPatterns::name(),
            'email' => FormPatterns::email($trainee->id),
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $updatedTrainee = $this->repository->updateForTenant($tenant, $id, $payload);

        return redirect()->route('admin.trainees.show', $updatedTrainee->id)
            ->with('status', 'Trainee atualizado com sucesso.');
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
