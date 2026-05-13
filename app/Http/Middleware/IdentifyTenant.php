<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Services\Tenant\TenantManager;
use App\Services\Tenant\PlatformTenantService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly PlatformTenantService $platformTenantService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('system-admin.*')) {
            return $next($request);
        }

        if ((bool) $request->user()?->isSystemAdmin()) {
            return $next($request);
        }

        $subdomain = $this->resolveSubdomain($request->getHost());

        if ($subdomain !== null) {
            $tenant = $this->tenantManager->setTenantBySlug($subdomain);

            if ($tenant === null) {
                if ($request->routeIs('landing.subdomain')) {
                    return $next($request);
                }

                abort(404, 'Tenant not found.');
            }

            $request->attributes->set('tenant', $tenant);

            return $next($request);
        }

        $selectedTenant = $this->resolveSelectedTenant($request);

        if ($selectedTenant !== null) {
            $request->attributes->set('tenant', $selectedTenant);

            return $next($request);
        }

        if (
            $request->user() !== null
            && ! $request->routeIs(
                'api.tenants.select',
                'api.tenants.select.store',
                'tenants.select',
                'tenants.select.store',
                'onboarding.profile.edit',
                'onboarding.profile.update',
                'onboarding.contractor'
            )
        ) {
            $user = $request->user();

            if ($user instanceof User && $user->needsProfileSelection()) {
                return $next($request);
            }

            if ($user instanceof User && $user->isTrainee()) {
                $defaultTenant = $this->platformTenantService->resolvePreferredTenantForTrainee($user);

                if ($defaultTenant instanceof Tenant) {
                    if ($request->hasSession()) {
                        $request->session()->put('tenant_id', $defaultTenant->id);
                    }

                    $request->attributes->set('tenant', $defaultTenant);

                    return $next($request);
                }

                return redirect()->route('tenants.select');
            }

            if ($user?->profileType() === Role::STUDENT || $user?->profileType() === Role::ADMIN) {
                return $next($request);
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Tenant selection required.',
                    'requiresTenantSelection' => true,
                ], 409);
            }

            return redirect()->route('tenants.select');
        }

        return $next($request);
    }

    private function resolveSelectedTenant(Request $request): ?Tenant
    {
        $slug = $request->header('X-Tenant-Slug');

        if (is_string($slug) && $slug !== '') {
            return $this->tenantManager->setTenantBySlug($slug);
        }

        if (! $request->hasSession()) {
            return null;
        }

        $selectedTenantId = (int) $request->session()->get('tenant_id', 0);

        if ($selectedTenantId <= 0) {
            return null;
        }

        $tenant = $this->tenantManager->setTenantById($selectedTenantId);

        if ($tenant === null) {
            $request->session()->forget('tenant_id');
        }

        return $tenant;
    }

    private function resolveSubdomain(string $host): ?string
    {
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        $configuredLandingDomain = env('APP_LANDING_ROOT_DOMAIN');
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        $rootHost = is_string($configuredLandingDomain) && $configuredLandingDomain !== ''
            ? $configuredLandingDomain
            : ((is_string($appHost) && $appHost !== '' && $appHost !== 'localhost') ? $appHost : null);

        if (! is_string($rootHost) || $rootHost === '') {
            return null;
        }

        if (! str_ends_with($host, '.' . $rootHost)) {
            return null;
        }

        $hostParts = explode('.', $host);
        $rootHostParts = explode('.', $rootHost);

        if (count($hostParts) <= count($rootHostParts)) {
            return null;
        }

        $candidate = $hostParts[0] ?? null;

        if ($candidate === null || in_array($candidate, ['www', 'api'], true)) {
            return null;
        }

        return $candidate;
    }
}
