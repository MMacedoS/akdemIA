<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\LegalDocuments;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePoliciesAccepted
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || $user->hasAcceptedRequiredPolicies()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Aceite dos termos de uso e da politica de privacidade obrigatorio para continuar.',
            'code' => 'policy_acceptance_required',
            'policies' => [
                'accepted' => false,
                'terms_accepted' => $user->hasAcceptedCurrentTerms(),
                'privacy_policy_accepted' => $user->hasAcceptedCurrentPrivacyPolicy(),
                ...LegalDocuments::documents(),
            ],
        ], 403);
    }
}
