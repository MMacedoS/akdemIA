<?php

namespace App\Repositories\Entities\Tenant;

use App\Models\Tenant\Tenant;
use App\Repositories\Contracts\Tenant\TenantRepositoryContract;
use Illuminate\Support\Collection;

class TenantRepository implements TenantRepositoryContract
{
    public function findBySlug(string $slug): ?Tenant
    {
        return Tenant::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    public function findById(int $id): ?Tenant
    {
        return Tenant::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function allSelectable(): Collection
    {
        return Tenant::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
