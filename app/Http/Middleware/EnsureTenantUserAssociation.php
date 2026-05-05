<?php

namespace App\Http\Middleware;

use App\Models\Tenant\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantUserAssociation
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            return response()->json([
                'message' => 'Tenant not identified.',
            ], 409);
        }

        if (! $user->belongsToTenant($tenant)) {
            return response()->json([
                'message' => 'User is not linked to tenant.',
            ], 403);
        }

        return $next($request);
    }
}
