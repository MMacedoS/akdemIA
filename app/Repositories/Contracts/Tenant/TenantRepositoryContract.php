<?php

namespace App\Repositories\Contracts\Tenant;

use App\Models\Tenant\Tenant;
use Illuminate\Support\Collection;

interface TenantRepositoryContract
{
    public function findBySlug(string $slug): ?Tenant;

    public function findById(int $id): ?Tenant;

    /**
     * @return Collection<int, Tenant>
     */
    public function allSelectable(): Collection;
}
