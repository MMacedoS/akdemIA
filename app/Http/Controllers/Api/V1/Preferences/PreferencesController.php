<?php

namespace App\Http\Controllers\Api\V1\Preferences;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Preferences\StorePreferenceRequest;
use App\Http\Requests\Api\V1\Preferences\UpdatePreferenceRequest;
use App\Models\Tenant\Tenant;
use App\Services\Preferences\PreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferencesController extends Controller
{
    public function __construct(
        private readonly PreferenceService $preferenceService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $this->allowsSelfService($user, $tenant)) {
            return response()->json([
                'message' => 'Forbidden for tenant context.',
            ], 403);
        }

        $preference = $this->preferenceService->getByUser($user);

        if ($preference === null) {
            return response()->json([
                'message' => 'Preferences not found.',
            ], 404);
        }

        return response()->json($preference);
    }

    public function store(StorePreferenceRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $this->allowsSelfService($user, $tenant)) {
            return response()->json([
                'message' => 'Forbidden for tenant context.',
            ], 403);
        }

        $preference = $this->preferenceService->createByUser($user, $request->validated());

        return response()->json($preference, 201);
    }

    public function update(UpdatePreferenceRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $this->allowsSelfService($user, $tenant)) {
            return response()->json([
                'message' => 'Forbidden for tenant context.',
            ], 403);
        }

        $preference = $this->preferenceService->updateByUser($user, $request->validated());

        if ($preference === null) {
            return response()->json([
                'message' => 'Preferences not found.',
            ], 404);
        }

        return response()->json($preference);
    }

    private function allowsSelfService($user, mixed $tenant): bool
    {
        if ($tenant instanceof Tenant) {
            return $user->belongsToTenant($tenant);
        }

        return $user->profileType() === Role::STUDENT;
    }
}
