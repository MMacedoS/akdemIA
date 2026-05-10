<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateMeRequest;
use App\Services\Students\StudentProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(
        private readonly StudentProfileService $studentProfileService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        if (! $this->studentProfileService->profileColumnsReady()) {
            return response()->json([
                'message' => 'Profile fields are not available yet. Run migrations.',
            ], 503);
        }

        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $this->studentProfileService->allowsSelfService($user, $tenant)) {
            return response()->json([
                'message' => 'User is not linked to tenant.',
            ], 403);
        }

        return response()->json($this->studentProfileService->profilePayload($user, $tenant));
    }

    public function update(UpdateMeRequest $request): JsonResponse
    {
        if (! $this->studentProfileService->profileColumnsReady()) {
            return response()->json([
                'message' => 'Profile fields are not available yet. Run migrations.',
            ], 503);
        }

        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $this->studentProfileService->allowsSelfService($user, $tenant)) {
            return response()->json([
                'message' => 'User is not linked to tenant.',
            ], 403);
        }

        $user->fill($request->validated());
        $user->save();

        return response()->json($this->studentProfileService->profilePayload($user, $tenant));
    }
}
