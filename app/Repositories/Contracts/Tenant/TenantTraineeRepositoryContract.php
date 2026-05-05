<?php

namespace App\Repositories\Contracts\Tenant;

use App\Models\Tenant\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TenantTraineeRepositoryContract
{
    public function paginateForTenant(Tenant $tenant, string $search = '', int $perPage = 10): LengthAwarePaginator;

    public function metricsForTenant(Tenant $tenant): array;

    public function createForTenant(Tenant $tenant, array $attributes, ?int $linkedByUserId): User;

    public function findInTenant(Tenant $tenant, int $traineeUserId): User;

    public function updateForTenant(Tenant $tenant, int $traineeUserId, array $attributes): User;

    public function optionsForTenant(Tenant $tenant): Collection;
}
