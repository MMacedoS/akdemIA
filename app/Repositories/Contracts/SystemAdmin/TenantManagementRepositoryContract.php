<?php

namespace App\Repositories\Contracts\SystemAdmin;

use App\Models\Tenant\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

interface TenantManagementRepositoryContract
{
    /**
     * @return Collection<int, User>
     */
    public function listAdminCandidates(): Collection;

    /**
     * @return Collection<int, Tenant>
     */
    public function listRecent(int $limit = 24): Collection;

    public function create(string $name, ?string $slug, string $accessEmail, string $defaultPassword): Tenant;

    public function createForExistingAdmin(
        User $accessUser,
        string $name,
        ?string $slug,
        ?string $contactEmail = null,
        ?string $contactPhone = null,
        ?string $documentNumber = null,
        ?string $notes = null,
    ): Tenant;

    public function findById(int $id): ?Tenant;

    public function update(Tenant $tenant, array $attributes): Tenant;
}
