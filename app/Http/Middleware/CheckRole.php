<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->forbiddenResponse($request, 'Unauthenticated.', 401);
        }

        $tenant = $request->attributes->get('tenant');

        $allowedRoles = collect($roles)
            ->map(fn(string $role) => Role::tryFrom($role))
            ->filter()
            ->values();

        if ($allowedRoles->isEmpty()) {
            return $this->forbiddenResponse($request, 'Role middleware is misconfigured.', 500);
        }

        if (! $tenant instanceof Tenant) {
            if ($allowedRoles->contains(Role::STUDENT) && $user->profileType() === Role::STUDENT) {
                return $next($request);
            }

            return $this->forbiddenResponse($request, 'Tenant not identified.', 409);
        }

        $currentRole = $user->getRole($tenant);

        if ($currentRole === null || ! $allowedRoles->contains($currentRole)) {
            return $this->forbiddenResponse($request, 'Forbidden.', 403);
        }

        return $next($request);
    }

    private function forbiddenResponse(Request $request, string $message, int $status): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], $status);
        }

        abort($status, $message);
    }
}
