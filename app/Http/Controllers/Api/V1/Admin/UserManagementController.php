<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Support\FormPatterns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            return response()->json([
                'message' => 'Tenant not identified.',
            ], 409);
        }

        $users = $tenant->users()
            ->select('users.id', 'users.name', 'users.email')
            ->selectRaw('tenant_user.role as role')
            ->orderBy('users.name')
            ->get();

        return response()->json([
            'data' => $users,
        ]);
    }

    public function storeStudent(Request $request): JsonResponse
    {
        return $this->storeByRole($request, Role::STUDENT);
    }

    public function storeTrainer(Request $request): JsonResponse
    {
        return $this->storeByRole($request, Role::TRAINER);
    }

    private function storeByRole(Request $request, Role $role): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            return response()->json([
                'message' => 'Tenant not identified.',
            ], 409);
        }

        $validated = $request->validate([
            'name' => FormPatterns::name(),
            'email' => FormPatterns::email(),
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => FormPatterns::normalizeEmail((string) $validated['email']),
            'password' => Hash::make($validated['password']),
        ]);

        $tenant->users()->attach($user->id, ['role' => $role->value]);

        return response()->json([
            'message' => ucfirst($role->value) . ' criado com sucesso.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role->value,
            ],
        ], 201);
    }
}
