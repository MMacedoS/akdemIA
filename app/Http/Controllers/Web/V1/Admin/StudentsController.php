<?php

namespace App\Http\Controllers\Web\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Web\V1\PanelUsersController;
use App\Models\Tenant\Tenant;
use App\Models\Workout\Workout;
use App\Repositories\Contracts\Tenant\TraineeStudentRepositoryContract;
use App\Support\FormPatterns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentsController extends PanelUsersController
{
    public function __construct(
        private readonly TraineeStudentRepositoryContract $repository,
    ) {}

    protected function role(): Role
    {
        return Role::STUDENT;
    }

    protected function viewBase(): string
    {
        return 'web.v1.admin.students';
    }

    protected function routePrefix(): string
    {
        return 'admin.students';
    }

    public function show(Request $request, int $id): View
    {
        $tenant = $this->resolveTenantContext($request);
        $user = $this->findUserInContextPublic($request, $id);

        $user->loadMissing(['physicalData', 'medicalData', 'preference']);

        $workouts = Workout::query()
            ->whereNull('tenant_id')
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(10)
            ->get();

        return view($this->viewBase() . '.show', [
            'user' => $user,
            'assignedTrainee' => $this->repository->assignedTraineeForStudent(null, $user->id),
            'workouts' => $workouts,
        ]);
    }

    public function index(Request $request): View
    {
        $tenant = $this->resolveTenantContext($request);
        $search = trim((string) $request->query('q', ''));

        return view($this->viewBase() . '.index', [
            'users' => $this->repository->paginateVisibleForTenant($tenant, $search),
            'search' => $search,
            'metrics' => $this->repository->metricsVisibleForTenant($tenant),
        ]);
    }

    public function create(): View
    {
        return view($this->viewBase() . '.create', [
            'traineeOptions' => $this->repository->traineeOptionsForTenant($this->resolveTenantContext(request())),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->resolveTenantContext($request);

        $payload = $request->validate([
            'name' => FormPatterns::name(),
            'email' => FormPatterns::email(),
            'password' => ['required', 'string', 'min:8'],
            'goal' => ['nullable', 'string', 'max:500'],
            'trainee_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $this->repository->createVisibleForTenant(
            $tenant,
            $payload,
            (int) $payload['trainee_user_id'],
            $request->user()?->id,
        );

        return redirect()->route('admin.students.index')
            ->with('status', 'Aluno criado com sucesso.');
    }

    public function edit(Request $request, int $id): View
    {
        $tenant = $this->resolveTenantContext($request);

        return view($this->viewBase() . '.edit', [
            'user' => $this->findUserInContextPublic($request, $id),
            'traineeOptions' => $this->repository->traineeOptionsForTenant($tenant),
            'assignedTrainee' => $this->repository->assignedTraineeForStudent(null, $id),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tenant = $this->resolveTenantContext($request);
        $student = $this->findUserInContextPublic($request, $id);

        $payload = $request->validate([
            'name' => FormPatterns::name(),
            'email' => FormPatterns::email($student->id),
            'password' => ['nullable', 'string', 'min:8'],
            'goal' => ['nullable', 'string', 'max:500'],
            'trainee_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $updatedStudent = $this->repository->updateVisibleForTenant(
            $tenant,
            $student->id,
            $payload,
            isset($payload['trainee_user_id']) ? (int) $payload['trainee_user_id'] : null,
            $request->user()?->id,
        );

        return redirect()->route($this->routePrefix() . '.show', $updatedStudent->id)
            ->with('status', 'Registro atualizado com sucesso.');
    }

    private function resolveTenantContext(Request $request): Tenant
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            abort(409, 'Tenant not identified.');
        }

        return $tenant;
    }

    private function findUserInContextPublic(Request $request, int $id)
    {
        $tenant = $this->resolveTenantContext($request);

        return $this->repository->findVisibleForTenant($tenant, $id);
    }
}
