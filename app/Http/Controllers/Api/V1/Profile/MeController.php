<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateMeRequest;
use App\Models\Tenant\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        if (! $this->profileColumnsReady()) {
            return response()->json([
                'message' => 'Profile fields are not available yet. Run migrations.',
            ], 503);
        }

        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $this->allowsSelfService($user, $tenant)) {
            return response()->json([
                'message' => 'User is not linked to tenant.',
            ], 403);
        }

        return response()->json([
            'id' => $user->id,
            'tenant_id' => $tenant instanceof Tenant ? $tenant->id : null,
            'name' => $user->name,
            'birth_date' => $user->birth_date?->toDateString(),
            'gender' => $user->gender,
            'height' => $user->height,
            'weight' => $user->weight,
            'goal' => $user->goal,
        ]);
    }

    public function update(UpdateMeRequest $request): JsonResponse
    {
        if (! $this->profileColumnsReady()) {
            return response()->json([
                'message' => 'Profile fields are not available yet. Run migrations.',
            ], 503);
        }

        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $this->allowsSelfService($user, $tenant)) {
            return response()->json([
                'message' => 'User is not linked to tenant.',
            ], 403);
        }

        $user->fill($request->validated());
        $user->save();

        return response()->json([
            'id' => $user->id,
            'tenant_id' => $tenant instanceof Tenant ? $tenant->id : null,
            'name' => $user->name,
            'birth_date' => $user->birth_date?->toDateString(),
            'gender' => $user->gender,
            'height' => $user->height,
            'weight' => $user->weight,
            'goal' => $user->goal,
        ]);
    }

    private function profileColumnsReady(): bool
    {
        return Schema::hasColumns('users', [
            'birth_date',
            'gender',
            'height',
            'weight',
            'goal',
        ]);
    }

    private function allowsSelfService($user, mixed $tenant): bool
    {
        if ($tenant instanceof Tenant) {
            return $user->belongsToTenant($tenant);
        }

        return $user->profileType() === Role::STUDENT;
    }
}
