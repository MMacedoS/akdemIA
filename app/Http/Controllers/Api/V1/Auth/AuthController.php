<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Repositories\Contracts\Tenant\TraineeStudentRepositoryContract;
use App\Services\Tenant\PlatformTenantService;
use App\Services\Tenant\Auth\TenantAuthService;
use App\Services\Tenant\TenantManager;
use App\Support\FormPatterns;
use App\Transformers\Tenant\TenantTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private readonly TenantAuthService $tenantAuthService,
        private readonly TenantManager $tenantManager,
        private readonly TenantTransformer $tenantTransformer,
        private readonly TraineeStudentRepositoryContract $traineeStudentRepository,
        private readonly PlatformTenantService $platformTenantService,
    ) {}

    public function registerStudent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => FormPatterns::name(),
            'email' => FormPatterns::email(),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $platformTrainee = $this->platformTenantService->resolvePlatformTrainee();
        $student = $this->traineeStudentRepository->createForTrainee(null, $platformTrainee->id, $validated);

        return $this->standaloneStudentResponse($student, 201, 'Aluno criado e vinculado ao trainer Plataforma.');
    }

    public function options(): JsonResponse
    {
        return response()->json([
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
            ],
            'guarantee' => [
                'token_contains_tenant_id' => true,
            ],
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
        ]);
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
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'assigned_trainer' => $assignedTrainee === null ? null : [
                'id' => $assignedTrainee->id,
                'name' => $assignedTrainee->name,
                'email' => $assignedTrainee->email,
            ],
        ], $status);
    }
}
