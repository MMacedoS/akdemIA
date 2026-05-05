<?php

namespace App\Http\Controllers\Api\V1\MedicalData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MedicalData\StoreMedicalDataRequest;
use App\Http\Requests\Api\V1\MedicalData\UpdateMedicalDataRequest;
use App\Models\Tenant\Tenant;
use App\Services\MedicalData\MedicalDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalDataController extends Controller
{
    public function __construct(
        private readonly MedicalDataService $medicalDataService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $tenant instanceof Tenant || ! $user->belongsToTenant($tenant)) {
            return response()->json([
                'message' => 'Forbidden for tenant context.',
            ], 403);
        }

        $medicalData = $this->medicalDataService->getByUser($user);

        if ($medicalData === null) {
            return response()->json([
                'message' => 'Medical data not found.',
            ], 404);
        }

        return response()->json($medicalData);
    }

    public function store(StoreMedicalDataRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $tenant instanceof Tenant || ! $user->belongsToTenant($tenant)) {
            return response()->json([
                'message' => 'Forbidden for tenant context.',
            ], 403);
        }

        $medicalData = $this->medicalDataService->createByUser($user, $request->validated());

        return response()->json($medicalData, 201);
    }

    public function update(UpdateMedicalDataRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $tenant instanceof Tenant || ! $user->belongsToTenant($tenant)) {
            return response()->json([
                'message' => 'Forbidden for tenant context.',
            ], 403);
        }

        $medicalData = $this->medicalDataService->updateByUser($user, $request->validated());

        if ($medicalData === null) {
            return response()->json([
                'message' => 'Medical data not found.',
            ], 404);
        }

        return response()->json($medicalData);
    }
}
