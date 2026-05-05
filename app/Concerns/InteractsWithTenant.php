<?php

namespace App\Concerns;

use App\Models\Tenant\Tenant;
use App\Services\Tenant\TenantManager;

trait InteractsWithTenant
{
    protected function currentTenant(): ?Tenant
    {
        return app(TenantManager::class)->getCurrentTenant();
    }

    protected function setCurrentTenant(Tenant $tenant): void
    {
        app(TenantManager::class)->setTenant($tenant);
    }

    protected function setCurrentTenantBySlug(string $slug): ?Tenant
    {
        /** @var TenantManager $tenantManager */
        $tenantManager = app(TenantManager::class);

        return $tenantManager->setTenantBySlug($slug);
    }

    protected function setCurrentTenantById(int $id): ?Tenant
    {
        /** @var TenantManager $tenantManager */
        $tenantManager = app(TenantManager::class);

        return $tenantManager->setTenantById($id);
    }
}
