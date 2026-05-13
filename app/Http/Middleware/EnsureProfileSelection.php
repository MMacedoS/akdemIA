<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileSelection
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->needsProfileSelection()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Selecao de perfil obrigatoria antes de continuar.',
                'code' => 'profile_selection_required',
                'available_profiles' => ['admin', 'trainer', 'student'],
            ], 409);
        }

        return redirect()->route('onboarding.profile.edit');
    }
}
