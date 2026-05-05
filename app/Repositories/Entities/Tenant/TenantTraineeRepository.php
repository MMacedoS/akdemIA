<?php

namespace App\Repositories\Entities\Tenant;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Repositories\Contracts\Tenant\TenantTraineeRepositoryContract;
use App\Support\FormPatterns;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantTraineeRepository implements TenantTraineeRepositoryContract
{
    public function paginateForTenant(Tenant $tenant, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        $query = User::query()
            ->select('users.*')
            ->join('tenant_trainee', function ($join) use ($tenant): void {
                $join->on('tenant_trainee.trainee_user_id', '=', 'users.id')
                    ->where('tenant_trainee.tenant_id', '=', $tenant->id);
            })
            ->whereIn('users.profile_type', [Role::TRAINER->value, 'trainee'])
            ->orderBy('users.name');

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery->where('users.name', 'like', '%' . $search . '%')
                    ->orWhere('users.email', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function metricsForTenant(Tenant $tenant): array
    {
        $baseQuery = User::query()
            ->join('tenant_trainee', function ($join) use ($tenant): void {
                $join->on('tenant_trainee.trainee_user_id', '=', 'users.id')
                    ->where('tenant_trainee.tenant_id', '=', $tenant->id);
            })
            ->whereIn('users.profile_type', [Role::TRAINER->value, 'trainee']);

        return [
            'total' => (clone $baseQuery)->count('users.id'),
            'active' => (clone $baseQuery)->where('users.is_active', true)->count('users.id'),
            'with_students' => DB::table('tenant_student_trainee_links')
                ->where('tenant_id', $tenant->id)
                ->distinct('trainee_user_id')
                ->count('trainee_user_id'),
        ];
    }

    public function createForTenant(Tenant $tenant, array $attributes, ?int $linkedByUserId): User
    {
        return DB::transaction(function () use ($tenant, $attributes, $linkedByUserId): User {
            $trainee = User::query()->create([
                'name' => trim((string) $attributes['name']),
                'email' => FormPatterns::normalizeEmail((string) $attributes['email']),
                'password' => (string) $attributes['password'],
                'profile_type' => Role::TRAINER->value,
                'is_active' => true,
                'is_system_admin' => false,
                'credits_balance' => 0,
            ]);

            DB::table('tenant_trainee')->updateOrInsert(
                [
                    'tenant_id' => $tenant->id,
                    'trainee_user_id' => $trainee->id,
                ],
                [
                    'linked_by_user_id' => $linkedByUserId,
                    'note' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            return $trainee;
        });
    }

    public function findInTenant(Tenant $tenant, int $traineeUserId): User
    {
        return User::query()
            ->select('users.*')
            ->join('tenant_trainee', function ($join) use ($tenant): void {
                $join->on('tenant_trainee.trainee_user_id', '=', 'users.id')
                    ->where('tenant_trainee.tenant_id', '=', $tenant->id);
            })
            ->whereIn('users.profile_type', [Role::TRAINER->value, 'trainee'])
            ->where('users.id', $traineeUserId)
            ->firstOrFail();
    }

    public function updateForTenant(Tenant $tenant, int $traineeUserId, array $attributes): User
    {
        $trainee = $this->findInTenant($tenant, $traineeUserId);

        $trainee->fill([
            'name' => trim((string) $attributes['name']),
            'email' => FormPatterns::normalizeEmail((string) $attributes['email']),
        ]);

        if (! empty($attributes['password'])) {
            $trainee->password = (string) $attributes['password'];
        }

        $trainee->save();

        return $trainee;
    }

    public function optionsForTenant(Tenant $tenant): Collection
    {
        return User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->join('tenant_trainee', function ($join) use ($tenant): void {
                $join->on('tenant_trainee.trainee_user_id', '=', 'users.id')
                    ->where('tenant_trainee.tenant_id', '=', $tenant->id);
            })
            ->whereIn('users.profile_type', [Role::TRAINER->value, 'trainee'])
            ->where('users.is_active', true)
            ->orderBy('users.name')
            ->get();
    }
}
