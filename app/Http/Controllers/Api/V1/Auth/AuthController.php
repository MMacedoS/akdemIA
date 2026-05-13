<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Repositories\Contracts\Tenant\TraineeStudentRepositoryContract;
use App\Support\LegalDocuments;
use App\Services\Tenant\PlatformTenantService;
use App\Services\Tenant\Auth\TenantAuthService;
use App\Services\Tenant\TenantManager;
use App\Support\FormPatterns;
use App\Transformers\Profile\StudentTrainerTransformer;
use App\Transformers\Tenant\TenantTransformer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(
        private readonly TenantAuthService $tenantAuthService,
        private readonly TenantManager $tenantManager,
        private readonly TenantTransformer $tenantTransformer,
        private readonly StudentTrainerTransformer $studentTrainerTransformer,
        private readonly TraineeStudentRepositoryContract $traineeStudentRepository,
        private readonly PlatformTenantService $platformTenantService,
    ) {}

    public function registerStudent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => FormPatterns::name(),
            'email' => FormPatterns::email(),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms_of_use' => ['accepted'],
            'privacy_policy' => ['accepted'],
        ]);

        $platformTrainee = $this->platformTenantService->resolvePlatformTrainee();
        $student = $this->traineeStudentRepository->createForTrainee(null, $platformTrainee->id, $validated);

        event(new Registered($student));

        return response()->json([
            'registered' => true,
            'requiresEmailVerification' => true,
            'verification_email_sent' => true,
            'message' => 'Aluno criado com sucesso. Confirme seu e-mail para liberar o login.',
            'profile' => Role::STUDENT->value,
            'policies' => $this->policyStatus($student),
            'user' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
            ],
            'assigned_trainer' => $this->studentTrainerTransformer->transformAssigned($platformTrainee),
        ], 201);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'audience' => 'student-mobile',
            'legal' => $this->legalDocuments(),
            'public_endpoints' => [
                [
                    'name' => 'register',
                    'description' => 'Cadastro publico de estudante para uso no app.',
                    'endpoint' => '/api/v1/auth/register',
                    'method' => 'POST',
                    'auth_required' => false,
                    'body' => [
                        'name' => 'Aluno App',
                        'email' => 'aluno@example.com',
                        'password' => 'password123',
                        'password_confirmation' => 'password123',
                        'terms_of_use' => true,
                        'privacy_policy' => true,
                    ],
                ],
                [
                    'name' => 'login',
                    'description' => 'Login principal do app. Para estudante standalone, retorna token final sem selecao de tenant.',
                    'endpoint' => '/api/v1/auth/login',
                    'method' => 'POST',
                    'auth_required' => false,
                    'body' => [
                        'email' => 'aluno@example.com',
                        'password' => 'password123',
                    ],
                ],
                [
                    'name' => 'google_login',
                    'description' => 'Login social do app com Google. Mantem o perfil estudante e pode exigir aceite explicito na primeira autenticacao.',
                    'endpoint' => '/api/v1/auth/google',
                    'method' => 'POST',
                    'auth_required' => false,
                    'body' => [
                        'access_token' => 'google-access-token',
                        'terms_of_use' => true,
                        'privacy_policy' => true,
                    ],
                    'response' => [
                        'authenticated' => true,
                        'profile' => 'student',
                        'token' => 'string',
                    ],
                ],
                [
                    'name' => 'terms',
                    'description' => 'Termos de uso atuais em pagina web publica.',
                    'endpoint' => '/termos-de-uso',
                    'method' => 'GET',
                    'auth_required' => false,
                ],
                [
                    'name' => 'privacy_policy',
                    'description' => 'Politica de privacidade atual em pagina web publica.',
                    'endpoint' => '/politica-de-privacidade',
                    'method' => 'GET',
                    'auth_required' => false,
                ],
            ],
            'authenticated_endpoints' => [
                [
                    'name' => 'accept_policies',
                    'description' => 'Registra o aceite atual de termos e politica depois do login.',
                    'endpoint' => '/api/v1/auth/accept-policies',
                    'method' => 'POST',
                    'auth_required' => true,
                    'body' => [
                        'terms_of_use' => true,
                        'privacy_policy' => true,
                    ],
                ],
                [
                    'name' => 'me',
                    'description' => 'Retorna o perfil autenticado do estudante.',
                    'endpoint' => '/api/v1/me',
                    'method' => 'GET',
                    'auth_required' => true,
                ],
                [
                    'name' => 'student_trainers',
                    'description' => 'Lista treinadores disponiveis para o estudante.',
                    'endpoint' => '/api/v1/me/trainers',
                    'method' => 'GET',
                    'auth_required' => true,
                ],
                [
                    'name' => 'change_trainer',
                    'description' => 'Troca o treinador vinculado do estudante.',
                    'endpoint' => '/api/v1/me/trainer',
                    'method' => 'PUT',
                    'auth_required' => true,
                    'body' => [
                        'trainee_user_id' => 1,
                    ],
                ],
            ],
            'scenarios' => [
                [
                    'name' => 'subdomain_login',
                    'description' => 'Acesso via subdominio. Tenant e identificado automaticamente.',
                    'endpoint' => '/api/v1/auth/login',
                    'method' => 'POST',
                    'headers' => [
                        'Host' => '{tenant}.seu-dominio.com',
                    ],
                    'body' => [
                        'email' => 'user@example.com',
                        'password' => 'secret',
                    ],
                    'response' => [
                        'token' => 'string',
                        'tenant' => ['id' => 1, 'name' => 'Tenant', 'slug' => 'tenant'],
                    ],
                ],
                [
                    'name' => 'no_subdomain_login',
                    'description' => 'Acesso sem subdominio. Retorna tenants vinculados para selecao.',
                    'endpoint' => '/api/v1/auth/login',
                    'method' => 'POST',
                    'body' => [
                        'email' => 'user@example.com',
                        'password' => 'secret',
                    ],
                    'response' => [
                        'requiresTenantSelection' => true,
                        'selectionToken' => 'string',
                        'tenants' => [['id' => 1, 'name' => 'Tenant', 'slug' => 'tenant']],
                    ],
                ],
                [
                    'name' => 'select_tenant',
                    'description' => 'Seleciona tenant apos login sem subdominio e gera token final.',
                    'endpoint' => '/api/v1/auth/select-tenant',
                    'method' => 'POST',
                    'body' => [
                        'selectionToken' => 'string',
                        'tenant_id' => 1,
                    ],
                    'response' => [
                        'token' => 'string',
                        'tenant' => ['id' => 1, 'name' => 'Tenant', 'slug' => 'tenant'],
                    ],
                ],
                [
                    'name' => 'google_first_login_without_policies',
                    'description' => 'Primeiro login Google sem aceite explicito. O app deve exibir o consentimento e reenviar o mesmo endpoint com os checkboxes marcados.',
                    'endpoint' => '/api/v1/auth/google',
                    'method' => 'POST',
                    'body' => [
                        'access_token' => 'google-access-token',
                    ],
                    'response' => [
                        'message' => 'Aceite dos termos de uso e da politica de privacidade obrigatorio para continuar.',
                        'code' => 'policy_acceptance_required',
                        'requiresPolicyAcceptance' => true,
                        'profile' => 'student',
                    ],
                ],
            ],
            'guarantee' => [
                'token_contains_tenant_id' => true,
            ],
        ]);
    }

    public function acceptPolicies(Request $request): JsonResponse
    {
        $request->validate([
            'terms_of_use' => ['accepted'],
            'privacy_policy' => ['accepted'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Usuario nao autenticado.',
            ], 401);
        }

        $user->acceptRequiredPolicies();

        return response()->json([
            'message' => 'Aceite registrado com sucesso.',
            'policies' => $this->policyStatus($user->fresh()),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Voce precisa confirmar seu e-mail antes de fazer login.',
            ], 403);
        }

        $tenant = $request->attributes->get('tenant');

        if ($tenant instanceof Tenant) {
            if ($user->getRole($tenant) === null) {
                return response()->json([
                    'message' => 'User is not linked to this tenant.',
                ], 403);
            }

            $token = $this->tenantAuthService->generateTenantToken($user, $tenant);

            return response()->json([
                'token' => $token,
                'tenant' => $this->tenantTransformer->transform($tenant),
                'policies' => $this->policyStatus($user),
            ]);
        }

        if ($user->profileType() === Role::STUDENT) {
            return $this->standaloneStudentResponse($user);
        }

        $tenants = $user->tenants()->get();

        if ($tenants->isEmpty()) {
            if ($user->isTrainer()) {
                return response()->json([
                    'authenticated' => true,
                    'profile' => 'trainer',
                    'requiresTenantSelection' => false,
                    'message' => 'Trainer autenticado sem vinculo de tenant.',
                    'policies' => $this->policyStatus($user),
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                ]);
            }

            return response()->json([
                'message' => 'User has no tenant linkage.',
            ], 403);
        }

        $selectionToken = $this->tenantAuthService->createSelectionToken($user);

        return response()->json([
            'requiresTenantSelection' => true,
            'selectionToken' => $selectionToken,
            'tenants' => $this->tenantTransformer->transformCollection($tenants),
            'policies' => $this->policyStatus($user),
        ]);
    }

    public function googleLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_token' => ['required', 'string'],
            'terms_of_use' => ['nullable', 'boolean'],
            'privacy_policy' => ['nullable', 'boolean'],
        ]);

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->userFromToken((string) $validated['access_token']);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Nao foi possivel autenticar com o Google.',
            ], 422);
        }

        $email = FormPatterns::normalizeEmail($googleUser->getEmail());
        $googleId = trim((string) $googleUser->getId());

        if ($email === null || $googleId === '') {
            return response()->json([
                'message' => 'A conta Google nao retornou um e-mail valido.',
            ], 422);
        }

        $platformTrainee = $this->platformTenantService->resolvePlatformTrainee();
        $user = User::query()
            ->where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        $acceptPoliciesInline = (bool) ($validated['terms_of_use'] ?? false) && (bool) ($validated['privacy_policy'] ?? false);

        if (! $user instanceof User) {
            $user = $this->traineeStudentRepository->createForTrainee(null, $platformTrainee->id, [
                'name' => trim((string) ($googleUser->getName() ?: 'Aluno Google')),
                'email' => $email,
                'password' => Str::password(32),
                'terms_of_use' => $acceptPoliciesInline,
                'privacy_policy' => $acceptPoliciesInline,
            ]);
        } else {
            if (! (bool) $user->is_active) {
                return response()->json([
                    'message' => 'Conta inativa para login no app.',
                ], 403);
            }

            if ($user->profileType() === null) {
                $user->profile_type = Role::STUDENT->value;
            }

            if ($user->profileType() !== Role::STUDENT) {
                return response()->json([
                    'message' => 'Esta conta nao pode usar o login Google do app porque nao pertence ao perfil estudante.',
                ], 403);
            }

            $user->forceFill([
                'google_id' => $googleId,
                'auth_provider' => 'google',
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            if ($acceptPoliciesInline && $user->needsPolicyAcceptance()) {
                $user->acceptRequiredPolicies();
            }

            if ($this->traineeStudentRepository->assignedTraineeForStudent(null, (int) $user->id) === null) {
                $this->traineeStudentRepository->reassignStudentTrainee(null, (int) $user->id, (int) $platformTrainee->id, (int) $platformTrainee->id);
            }
        }

        $user->forceFill([
            'google_id' => $googleId,
            'auth_provider' => 'google',
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if ($user->needsPolicyAcceptance()) {
            return $this->policyAcceptanceRequiredResponse($user);
        }

        return $this->standaloneStudentResponse($user->fresh(), 200, 'Aluno autenticado com Google sem vinculo de tenant.');
    }

    public function selectTenant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'selectionToken' => ['required', 'string'],
            'tenant_id' => ['required', 'integer'],
        ]);

        $userId = $this->tenantAuthService->consumeSelectionToken($validated['selectionToken']);

        if ($userId === null) {
            return response()->json([
                'message' => 'Invalid or expired selection token.',
            ], 401);
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        $tenant = $this->tenantManager->setTenantById((int) $validated['tenant_id']);

        if ($tenant === null || $user->getRole($tenant) === null) {
            return response()->json([
                'message' => 'Tenant is not linked to the user.',
            ], 403);
        }

        $token = $this->tenantAuthService->generateTenantToken($user, $tenant);

        return response()->json([
            'token' => $token,
            'tenant' => $this->tenantTransformer->transform($tenant),
            'policies' => $this->policyStatus($user),
        ]);
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::query()->find($id);

        if ($user === null) {
            return response()->json([
                'message' => 'Usuario nao encontrado.',
            ], 404);
        }

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Link de verificacao invalido.',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'verified' => true,
                'message' => 'E-mail ja confirmado.',
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'verified' => true,
            'message' => 'E-mail confirmado com sucesso.',
        ]);
    }

    private function standaloneStudentResponse(User $user, int $status = 200, string $message = 'Aluno autenticado sem vinculo de tenant.'): JsonResponse
    {
        $assignedTrainee = $this->traineeStudentRepository->assignedTraineeForStudent(null, (int) $user->id);

        return response()->json([
            'authenticated' => true,
            'profile' => Role::STUDENT->value,
            'requiresTenantSelection' => false,
            'message' => $message,
            'token' => $this->tenantAuthService->generateStandaloneToken($user),
            'policies' => $this->policyStatus($user),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_add_credit' => (bool) $user->is_add_credit,
            ],
            'assigned_trainer' => $assignedTrainee === null ? null : $this->studentTrainerTransformer->transformAssigned($assignedTrainee),
        ], $status);
    }

    private function policyAcceptanceRequiredResponse(User $user): JsonResponse
    {
        return response()->json([
            'message' => 'Aceite dos termos de uso e da politica de privacidade obrigatorio para continuar.',
            'code' => 'policy_acceptance_required',
            'requiresPolicyAcceptance' => true,
            'profile' => Role::STUDENT->value,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'policies' => $this->policyStatus($user),
        ], 409);
    }

    /**
     * @return array<string, mixed>
     */
    private function policyStatus(User $user): array
    {
        return [
            'accepted' => $user->hasAcceptedRequiredPolicies(),
            'terms_accepted' => $user->hasAcceptedCurrentTerms(),
            'privacy_policy_accepted' => $user->hasAcceptedCurrentPrivacyPolicy(),
            'terms_accepted_at' => optional($user->terms_accepted_at)?->toIso8601String(),
            'privacy_policy_accepted_at' => optional($user->privacy_policy_accepted_at)?->toIso8601String(),
            ...$this->legalDocuments(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function legalDocuments(): array
    {
        return LegalDocuments::documents();
    }
}
