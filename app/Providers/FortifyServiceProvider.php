<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Services\Tenant\PlatformTenantService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureEmailVerification();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::authenticateUsing(function (Request $request): ?User {
            $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
                'tenant_slug' => ['nullable', 'string', 'max:100'],
            ]);

            $user = User::query()
                ->where('email', strtolower((string) $request->string('email')))
                ->first();

            if ($user === null || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
                return null;
            }

            if (! (bool) $user->is_active) {
                return null;
            }

            if (! $user->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'email' => 'Voce precisa confirmar seu e-mail antes de entrar.',
                ]);
            }

            if (! $user->isSystemAdmin() && $request->hasSession()) {
                $intendedUrl = (string) $request->session()->get('url.intended', '');

                if ($intendedUrl !== '' && str_contains($intendedUrl, '/system-admin/')) {
                    $request->session()->forget('url.intended');
                }
            }

            $tenantSlug = trim((string) $request->input('tenant_slug', ''));

            if ($tenantSlug === '') {
                if ($user->isTrainee()) {
                    $defaultTenant = app(PlatformTenantService::class)->resolvePreferredTenantForTrainee($user);

                    if ($defaultTenant instanceof Tenant) {
                        $request->session()->put('tenant_id', $defaultTenant->id);
                    }

                    return $user;
                }

                throw ValidationException::withMessages([
                    'tenant_slug' => 'Tenant obrigatorio para este usuario.',
                ]);
            }

            $tenant = Tenant::query()
                ->where('slug', $tenantSlug)
                ->where('is_active', true)
                ->first();

            $isAllowedForTenant = $user->isTrainee()
                ? $user->traineeTenants()->where('tenants.id', $tenant?->id)->exists()
                : ($tenant instanceof Tenant && $user->belongsToTenant($tenant));

            if ($tenant === null || ! $isAllowedForTenant) {
                throw ValidationException::withMessages([
                    'tenant_slug' => 'Tenant invalido para este usuario.',
                ]);
            }

            $request->session()->put('tenant_id', $tenant->id);

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn(Request $request) => view('auth.login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
            'tenants' => Tenant::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['name', 'slug']),
        ]));

        Fortify::resetPasswordView(fn(Request $request) => view('auth.reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn(Request $request) => view('auth.forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn(Request $request) => view('auth.verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn() => view('auth.register'));

        Fortify::twoFactorChallengeView(fn() => view('auth.two-factor-challenge'));

        Fortify::confirmPasswordView(fn() => view('auth.confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    private function configureEmailVerification(): void
    {
        VerifyEmail::createUrlUsing(function (User $user): string {
            return URL::temporarySignedRoute(
                'api.auth.verify-email',
                now()->addMinutes(60),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
            );
        });
    }
}
