<?php

namespace App\Http\Controllers\Web\V1\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\Contracts\SystemAdmin\TenantManagementRepositoryContract;
use App\Support\FormPatterns;
use App\Services\Tenant\PlatformTenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileOnboardingController extends Controller
{
    public function __construct(
        private readonly PlatformTenantService $platformTenantService,
        private readonly TenantManagementRepositoryContract $tenantManagementRepository,
    ) {}

    public function edit(Request $request): View|RedirectResponse
    {
        $user = $this->resolveUser($request);

        if (! $user->needsProfileSelection()) {
            return $this->redirectAfterSelection($user);
        }

        return view('auth.profile-selection', [
            'selectedProfile' => old('profile_type'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->resolveUser($request);

        if (! $user->needsProfileSelection()) {
            return $this->redirectAfterSelection($user);
        }

        $payload = $request->validate([
            'profile_type' => ['required', 'string', Rule::in([
                Role::ADMIN->value,
                Role::TRAINER->value,
                Role::STUDENT->value,
            ])],
        ]);

        $selectedRole = Role::from($payload['profile_type']);

        $user->forceFill([
            'profile_type' => $selectedRole->value,
        ])->save();

        if ($selectedRole === Role::TRAINER) {
            $this->platformTenantService->attachTraineeToPlatform($user);
        }

        return $this->redirectAfterSelection($user->fresh(), true);
    }

    public function contractor(Request $request): View|RedirectResponse
    {
        $user = $this->resolveUser($request);

        if ($user->needsProfileSelection()) {
            return redirect()->route('onboarding.profile.edit');
        }

        if ($user->profileType() !== Role::ADMIN) {
            return redirect()->route('dashboard');
        }

        if ($user->tenants()->where('is_active', true)->exists()) {
            return redirect()->route('tenants.select');
        }

        return view('auth.contractor-pending', [
            'defaultTenantName' => old('name', $user->name),
            'defaultContactEmail' => old('contact_email', $user->email),
            'defaultSlug' => old('slug'),
        ]);
    }

    public function storeContractor(Request $request): RedirectResponse
    {
        $user = $this->resolveUser($request);

        if ($user->needsProfileSelection()) {
            return redirect()->route('onboarding.profile.edit');
        }

        abort_unless($user->profileType() === Role::ADMIN, 403);

        if ($user->tenants()->where('is_active', true)->exists()) {
            return redirect()->route('tenants.select');
        }

        $validated = $request->validate([
            'name' => FormPatterns::name(true, 120),
            'slug' => FormPatterns::slug(false, 120),
            'contact_email' => FormPatterns::looseEmail(false, 190),
        ]);

        $tenant = $this->tenantManagementRepository->createForExistingAdmin(
            $user,
            (string) $validated['name'],
            isset($validated['slug']) ? (string) $validated['slug'] : null,
            $validated['contact_email'] ?? null,
        );

        if ($request->hasSession()) {
            $request->session()->put('tenant_id', $tenant->id);
        }

        return redirect()->route('dashboard')
            ->with('status', 'Tenant criado e vinculado com sucesso.');
    }

    private function resolveUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function redirectAfterSelection(User $user, bool $showSuccess = false): RedirectResponse
    {
        $target = match ($user->profileType()) {
            Role::ADMIN => route('onboarding.contractor'),
            default => route('dashboard'),
        };

        $redirect = redirect()->to($target);

        if ($showSuccess) {
            $redirect->with('status', 'Perfil inicial salvo com sucesso.');
        }

        return $redirect;
    }
}
