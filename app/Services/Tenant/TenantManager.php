<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Tenant;
use App\Repositories\Contracts\Tenant\TenantRepositoryContract;
use App\Transformers\Tenant\TenantTransformer;

class TenantManager
{
    public function __construct(
        private readonly TenantRepositoryContract $tenantRepository,
        private readonly TenantTransformer $tenantTransformer,
    ) {}

    private ?Tenant $currentTenant = null;

    public function getCurrentTenant(): ?Tenant
    {
        return $this->currentTenant;
    }

    public function setTenant(Tenant $tenant): void
    {
        $this->currentTenant = $tenant;

        app()->instance(Tenant::class, $tenant);
        app()->instance('tenant', $tenant);
    }

    public function setTenantBySlug(string $slug): ?Tenant
    {
        $tenant = $this->tenantRepository->findBySlug($slug);

        if ($tenant === null) {
            return null;
        }

        $this->setTenant($tenant);

        return $tenant;
    }

    public function setTenantById(int $id): ?Tenant
    {
        $tenant = $this->tenantRepository->findById($id);

        if ($tenant === null) {
            return null;
        }

        $this->setTenant($tenant);

        return $tenant;
    }

    /**
     * @return array<int, array{id:int,name:string,slug:string}>
     */
    public function listSelectableTenants(): array
    {
        return $this->tenantTransformer->transformCollection(
            $this->tenantRepository->allSelectable(),
        );
    }

    /**
     * @return array{id:int,name:string,slug:string}|null
     */
    public function transformTenant(?Tenant $tenant): ?array
    {
        if ($tenant === null) {
            return null;
        }

        return $this->tenantTransformer->transform($tenant);
    }
}
