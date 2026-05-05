<?php

namespace App\Http\Controllers\Web\V1\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantSelectionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        $tenants = $user->isTrainee()
            ? $user->traineeTenants()->where('is_active', true)->orderBy('name')->get(['tenants.id', 'tenants.name', 'tenants.slug'])
            : $user->tenants()->where('is_active', true)->orderBy('name')->get(['tenants.id', 'tenants.name', 'tenants.slug']);

        return view('web.v1.tenants.select', [
            'tenants' => $tenants,
            'selectedTenantId' => (int) $request->session()->get('tenant_id', 0),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        $payload = $request->validate([
            'slug' => ['required', 'string', 'max:100'],
        ]);

        $tenantRelation = $user->isTrainee() ? $user->traineeTenants() : $user->tenants();

        $tenant = $tenantRelation
            ->where('is_active', true)
            ->where('slug', $payload['slug'])
            ->first(['tenants.id', 'tenants.name', 'tenants.slug']);

        if (! $tenant instanceof Tenant) {
            return back()->withErrors([
                'slug' => 'Tenant nao encontrado para este usuario.',
            ])->withInput();
        }

        $request->session()->put('tenant_id', $tenant->id);

        return redirect()->route('dashboard');
    }
}
