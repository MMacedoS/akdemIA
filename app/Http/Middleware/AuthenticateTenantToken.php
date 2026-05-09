<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTenantToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! is_string($bearerToken) || $bearerToken === '') {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $parts = explode('.', $bearerToken, 2);

        if (count($parts) !== 2) {
            return response()->json([
                'message' => 'Invalid token.',
            ], 401);
        }

        [$basePayload, $signature] = $parts;

        $expectedSignature = hash_hmac('sha256', $basePayload, (string) config('app.key'));

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'message' => 'Invalid token signature.',
            ], 401);
        }

        $decodedPayload = base64_decode($basePayload, true);

        if (! is_string($decodedPayload)) {
            return response()->json([
                'message' => 'Invalid token payload.',
            ], 401);
        }

        $payload = json_decode($decodedPayload, true);

        if (! is_array($payload)) {
            return response()->json([
                'message' => 'Invalid token payload.',
            ], 401);
        }

        $userId = (int) ($payload['user_id'] ?? 0);
        $tenantId = array_key_exists('tenant_id', $payload) ? (int) $payload['tenant_id'] : null;
        $role = isset($payload['role']) ? (string) $payload['role'] : null;
        $profile = isset($payload['profile']) ? (string) $payload['profile'] : null;

        if ($userId <= 0) {
            return response()->json([
                'message' => 'Invalid token claims.',
            ], 401);
        }

        $user = User::query()->find($userId);

        if ($user === null || ! (bool) $user->is_active) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (($tenantId === null || $tenantId <= 0) && $profile === Role::STUDENT->value && $user->profileType() === Role::STUDENT) {
            $request->setUserResolver(fn() => $user);

            return $next($request);
        }

        $tenant = Tenant::query()->where('id', $tenantId)->where('is_active', true)->first();

        if ($tenant === null || $role === null || $role === '') {
            return response()->json([
                'message' => 'Invalid token claims.',
            ], 401);
        }

        $currentRole = $user->getRole($tenant);

        if ($currentRole === null || $currentRole->value !== $role) {
            return response()->json([
                'message' => 'Invalid tenant context.',
            ], 403);
        }

        $request->setUserResolver(fn() => $user);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
