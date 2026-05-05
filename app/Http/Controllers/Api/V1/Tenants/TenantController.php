<?php

namespace App\Http\Controllers\Api\V1\Tenants;

use App\Http\Controllers\Controller;
use App\Services\Tenant\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'selectedTenantId' => $request->session()->get('tenant_id'),
            'tenants' => $this->tenantManager->listSelectableTenants(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100'],
        ]);

        $tenant = $this->tenantManager->setTenantBySlug($validated['slug']);

        if ($tenant === null) {
            return response()->json([
                'slug' => 'Tenant not found.',
            ], 404);
        }

        $request->session()->put('tenant_id', $tenant->id);

        return response()->json([
            'tenant' => $this->tenantManager->transformTenant($tenant),
        ]);
    }
}
