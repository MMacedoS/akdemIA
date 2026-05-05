<?php

namespace App\Http\Controllers\Api\V1\PhysicalData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PhysicalData\StorePhysicalDataRequest;
use App\Http\Requests\Api\V1\PhysicalData\UpdatePhysicalDataRequest;
use App\Models\Tenant\Tenant;
use App\Services\PhysicalData\PhysicalDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhysicalDataController extends Controller
{
    public function __construct(
        private readonly PhysicalDataService $physicalDataService,
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

        $physicalData = $this->physicalDataService->getByUser($user);

        if ($physicalData === null) {
            return response()->json([
                'message' => 'Physical data not found.',
            ], 404);
        }

        return response()->json($physicalData);
    }

    public function store(StorePhysicalDataRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $tenant instanceof Tenant || ! $user->belongsToTenant($tenant)) {
            return response()->json([
                'message' => 'Forbidden for tenant context.',
            ], 403);
        }

        $physicalData = $this->physicalDataService->createByUser($user, $request->validated());

        return response()->json($physicalData, 201);
    }

    public function update(UpdatePhysicalDataRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');

        if ($user === null || ! $tenant instanceof Tenant || ! $user->belongsToTenant($tenant)) {
            return response()->json([
                'message' => 'Forbidden for tenant context.',
            ], 403);
        }

        $physicalData = $this->physicalDataService->updateByUser($user, $request->validated());

        if ($physicalData === null) {
            return response()->json([
                'message' => 'Physical data not found.',
            ], 404);
        }

        return response()->json($physicalData);
    }
}
