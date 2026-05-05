<?php

namespace App\Repositories\Contracts\SystemAdmin;

use App\Models\User;
use Illuminate\Support\Collection;

interface TraineeManagementRepositoryContract
{
    /**
     * @return Collection<int, User>
     */
    public function listRecent(int $limit = 24): Collection;

    public function create(string $name, string $email, string $password): User;

    /**
     * @return Collection<int, \App\Models\Tenant\Tenant>
     */
    public function listTenantOptions(): Collection;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listRecentLinks(int $limit = 24): Collection;

    public function linkToTenant(int $traineeUserId, int $tenantId, ?int $linkedByUserId, ?string $note): void;
}
