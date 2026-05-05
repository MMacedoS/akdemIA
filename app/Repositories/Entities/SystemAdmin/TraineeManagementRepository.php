<?php

namespace App\Repositories\Entities\SystemAdmin;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Repositories\Contracts\SystemAdmin\TraineeManagementRepositoryContract;
use App\Support\FormPatterns;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TraineeManagementRepository implements TraineeManagementRepositoryContract
{
    public function listRecent(int $limit = 24): Collection
    {
        return User::query()
            ->where('profile_type', Role::TRAINER->value)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'is_active', 'created_at']);
    }

    public function create(string $name, string $email, string $password): User
    {
        return User::query()->create([
            'name' => trim($name),
            'email' => FormPatterns::normalizeEmail($email),
            'password' => $password,
            'profile_type' => Role::TRAINER->value,
            'is_active' => true,
            'is_system_admin' => false,
            'credits_balance' => 0,
        ]);
    }

    public function listTenantOptions(): Collection
    {
        return Tenant::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    public function listRecentLinks(int $limit = 24): Collection
    {
        return DB::table('tenant_trainee')
            ->join('users as trainees', 'trainees.id', '=', 'tenant_trainee.trainee_user_id')
            ->join('tenants', 'tenants.id', '=', 'tenant_trainee.tenant_id')
            ->leftJoin('users as actors', 'actors.id', '=', 'tenant_trainee.linked_by_user_id')
            ->orderByDesc('tenant_trainee.id')
            ->limit($limit)
            ->get([
                'tenant_trainee.id',
                'trainees.name as trainee_name',
                'trainees.email as trainee_email',
                'tenants.name as tenant_name',
                'tenants.slug as tenant_slug',
                'actors.email as linked_by_email',
                'tenant_trainee.note',
                'tenant_trainee.created_at',
            ]);
    }

    public function linkToTenant(int $traineeUserId, int $tenantId, ?int $linkedByUserId, ?string $note): void
    {
        $existing = DB::table('tenant_trainee')
            ->where('tenant_id', $tenantId)
            ->where('trainee_user_id', $traineeUserId)
            ->exists();

        if ($existing) {
            DB::table('tenant_trainee')
                ->where('tenant_id', $tenantId)
                ->where('trainee_user_id', $traineeUserId)
                ->update([
                    'linked_by_user_id' => $linkedByUserId,
                    'note' => $this->nullableString($note),
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('tenant_trainee')->insert([
            'tenant_id' => $tenantId,
            'trainee_user_id' => $traineeUserId,
            'linked_by_user_id' => $linkedByUserId,
            'note' => $this->nullableString($note),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
