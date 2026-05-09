<?php

namespace App\Repositories\Contracts\Tenant;

use App\Models\Tenant\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TraineeStudentRepositoryContract
{
    public function paginateForTenant(Tenant $tenant, string $search = '', int $perPage = 10): LengthAwarePaginator;

    public function paginateVisibleForTenant(Tenant $tenant, string $search = '', int $perPage = 10): LengthAwarePaginator;

    public function paginateForTrainee(?Tenant $tenant, int $traineeUserId, string $search = '', int $perPage = 10): LengthAwarePaginator;

    public function metricsForTenant(Tenant $tenant): array;

    public function metricsVisibleForTenant(Tenant $tenant): array;

    public function metricsForTrainee(?Tenant $tenant, int $traineeUserId): array;

    public function createForTenant(Tenant $tenant, array $attributes, ?int $traineeUserId, ?int $linkedByUserId): User;

    public function createVisibleForTenant(Tenant $tenant, array $attributes, int $traineeUserId, ?int $linkedByUserId): User;

    public function createForTrainee(?Tenant $tenant, int $traineeUserId, array $attributes): User;

    public function findInTenant(Tenant $tenant, int $studentUserId): User;

    public function findVisibleForTenant(Tenant $tenant, int $studentUserId): User;

    public function findForTrainee(?Tenant $tenant, int $traineeUserId, int $studentUserId): User;

    public function updateForTenant(Tenant $tenant, int $studentUserId, array $attributes, ?int $traineeUserId, ?int $linkedByUserId): User;

    public function updateVisibleForTenant(Tenant $tenant, int $studentUserId, array $attributes, ?int $traineeUserId, ?int $linkedByUserId): User;

    public function updateForTrainee(?Tenant $tenant, int $traineeUserId, int $studentUserId, array $attributes): User;

    public function traineeOptionsForTenant(Tenant $tenant): Collection;

    public function availableStandaloneTrainees(): Collection;

    public function paginateStandaloneTrainees(string $search = '', int $perPage = 15): LengthAwarePaginator;

    public function recentForTrainee(?Tenant $tenant, int $traineeUserId, int $limit = 8): Collection;

    public function assignedTraineeForStudent(?Tenant $tenant, int $studentUserId): ?User;

    public function reassignStudentTrainee(?Tenant $tenant, int $studentUserId, int $traineeUserId, ?int $linkedByUserId): void;
}
