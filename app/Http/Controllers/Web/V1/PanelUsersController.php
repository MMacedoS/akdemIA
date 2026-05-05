<?php

namespace App\Http\Controllers\Web\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Support\FormPatterns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

abstract class PanelUsersController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->resolveTenant($request);
        $search = trim((string) $request->query('q', ''));

        $users = $this->usersQuery($tenant, $search)
            ->paginate(10)
            ->withQueryString();

        $total = $this->usersBaseQuery($tenant)->count();
        $verified = $this->usersBaseQuery($tenant)->whereNotNull('users.email_verified_at')->count();
        $withGoal = $this->usersBaseQuery($tenant)->whereNotNull('users.goal')->count();

        return view($this->viewBase() . '.index', [
            'users' => $users,
            'search' => $search,
            'metrics' => [
                'total' => $total,
                'verified' => $verified,
                'with_goal' => $withGoal,
            ],
        ]);
    }

    public function create(): View
    {
        return view($this->viewBase() . '.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);

        $payload = $request->validate([
            'name' => FormPatterns::name(),
            'email' => FormPatterns::email(),
            'password' => ['required', 'string', 'min:8'],
            'goal' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($tenant, $payload): void {
            $user = User::query()->create([
                'name' => $payload['name'],
                'email' => FormPatterns::normalizeEmail((string) $payload['email']),
                'password' => $payload['password'],
                'goal' => $payload['goal'] ?? null,
            ]);

            $tenant->users()->attach($user->id, [
                'role' => $this->role()->value,
            ]);
        });

        return redirect()->route($this->routePrefix() . '.index')
            ->with('status', 'Registro criado com sucesso.');
    }

    public function show(Request $request, int $id): View
    {
        $tenant = $this->resolveTenant($request);
        $user = $this->findUserInContext($tenant, $id);

        return view($this->viewBase() . '.show', [
            'user' => $user,
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        $tenant = $this->resolveTenant($request);
        $user = $this->findUserInContext($tenant, $id);

        return view($this->viewBase() . '.edit', [
            'user' => $user,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);
        $user = $this->findUserInContext($tenant, $id);

        $payload = $request->validate([
            'name' => FormPatterns::name(),
            'email' => FormPatterns::email($user->id),
            'password' => ['nullable', 'string', 'min:8'],
            'goal' => ['nullable', 'string', 'max:500'],
        ]);

        $user->name = $payload['name'];
        $user->email = FormPatterns::normalizeEmail((string) $payload['email']);
        $user->goal = $payload['goal'] ?? null;

        if (! empty($payload['password'])) {
            $user->password = $payload['password'];
        }

        $user->save();

        return redirect()->route($this->routePrefix() . '.show', $user->id)
            ->with('status', 'Registro atualizado com sucesso.');
    }

    abstract protected function role(): Role;

    abstract protected function viewBase(): string;

    abstract protected function routePrefix(): string;

    private function resolveTenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            abort(409, 'Tenant not identified.');
        }

        return $tenant;
    }

    private function usersBaseQuery(Tenant $tenant): BelongsToMany
    {
        return $tenant->users()
            ->wherePivot('role', $this->role()->value)
            ->select('users.*');
    }

    private function usersQuery(Tenant $tenant, string $search): BelongsToMany
    {
        $query = $this->usersBaseQuery($tenant)
            ->orderBy('users.name');

        if ($search !== '') {
            $query->where(function (Builder $innerQuery) use ($search): void {
                $innerQuery->where('users.name', 'like', '%' . $search . '%')
                    ->orWhere('users.email', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    private function findUserInContext(Tenant $tenant, int $id): User
    {
        return $this->usersBaseQuery($tenant)
            ->where('users.id', $id)
            ->firstOrFail();
    }
}
