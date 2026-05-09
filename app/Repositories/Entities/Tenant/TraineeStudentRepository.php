<?php

namespace App\Repositories\Entities\Tenant;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantStudentTraineeLink;
use App\Models\User;
use App\Repositories\Contracts\Tenant\TraineeStudentRepositoryContract;
use App\Support\FormPatterns;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TraineeStudentRepository implements TraineeStudentRepositoryContract
{
    public function paginateForTenant(Tenant $tenant, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        $query = $tenant->users()
            ->wherePivot('role', Role::STUDENT->value)
            ->select('users.*')
            ->orderBy('users.name');

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery->where('users.name', 'like', '%' . $search . '%')
                    ->orWhere('users.email', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function paginateVisibleForTenant(Tenant $tenant, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->visibleStandaloneStudentsForTenantQuery($tenant)
            ->orderBy('users.name');

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery->where('users.name', 'like', '%' . $search . '%')
                    ->orWhere('users.email', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function paginateForTrainee(?Tenant $tenant, int $traineeUserId, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        $query = User::query()
            ->join('tenant_student_trainee_links', function ($join) use ($tenant, $traineeUserId): void {
                $join->on('tenant_student_trainee_links.student_user_id', '=', 'users.id')
                    ->where('tenant_student_trainee_links.trainee_user_id', '=', $traineeUserId);

                if ($tenant instanceof Tenant) {
                    $join->where('tenant_student_trainee_links.tenant_id', '=', $tenant->id);
                } else {
                    $join->whereNull('tenant_student_trainee_links.tenant_id');
                }
            })
            ->where('users.profile_type', Role::STUDENT->value)
            ->select('users.*')
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
        return [
            'total' => $tenant->users()->wherePivot('role', Role::STUDENT->value)->count(),
            'verified' => $tenant->users()->wherePivot('role', Role::STUDENT->value)->whereNotNull('users.email_verified_at')->count(),
            'with_goal' => $tenant->users()->wherePivot('role', Role::STUDENT->value)->whereNotNull('users.goal')->count(),
        ];
    }

    public function metricsVisibleForTenant(Tenant $tenant): array
    {
        $baseQuery = $this->visibleStandaloneStudentsForTenantQuery($tenant);

        return [
            'total' => (clone $baseQuery)->count('users.id'),
            'verified' => (clone $baseQuery)->whereNotNull('users.email_verified_at')->count('users.id'),
            'with_goal' => (clone $baseQuery)->whereNotNull('users.goal')->count('users.id'),
        ];
    }

    public function metricsForTrainee(?Tenant $tenant, int $traineeUserId): array
    {
        $baseQuery = DB::table('tenant_student_trainee_links')
            ->join('users', 'users.id', '=', 'tenant_student_trainee_links.student_user_id')
            ->where('tenant_student_trainee_links.trainee_user_id', $traineeUserId);

        if ($tenant instanceof Tenant) {
            $baseQuery->where('tenant_student_trainee_links.tenant_id', $tenant->id);
        } else {
            $baseQuery->whereNull('tenant_student_trainee_links.tenant_id');
        }

        return [
            'total' => (clone $baseQuery)->count('users.id'),
            'verified' => (clone $baseQuery)->whereNotNull('users.email_verified_at')->count('users.id'),
            'with_goal' => (clone $baseQuery)->whereNotNull('users.goal')->count('users.id'),
        ];
    }

    public function createForTenant(Tenant $tenant, array $attributes, ?int $traineeUserId, ?int $linkedByUserId): User
    {
        return DB::transaction(function () use ($tenant, $attributes, $traineeUserId, $linkedByUserId): User {
            $student = User::query()->create([
                'name' => trim((string) $attributes['name']),
                'email' => FormPatterns::normalizeEmail((string) $attributes['email']),
                'password' => (string) $attributes['password'],
                'goal' => $this->nullableString($attributes['goal'] ?? null),
                'birth_date' => $this->nullableString($attributes['birth_date'] ?? null),
                'height' => $this->nullableNumeric($attributes['height'] ?? null),
                'weight' => $this->nullableNumeric($attributes['weight'] ?? null),
                'profile_type' => Role::STUDENT->value,
                'is_active' => true,
                'is_system_admin' => false,
                'credits_balance' => 0,
            ]);

            $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);
            $this->syncStudentTraineeLink($tenant, $student->id, $traineeUserId, $linkedByUserId);

            return $student;
        });
    }

    public function createVisibleForTenant(Tenant $tenant, array $attributes, int $traineeUserId, ?int $linkedByUserId): User
    {
        $this->assertTraineeBelongsToTenant($tenant, $traineeUserId);

        return $this->createStandaloneStudent($attributes, $traineeUserId, $linkedByUserId);
    }

    public function createForTrainee(?Tenant $tenant, int $traineeUserId, array $attributes): User
    {
        if ($tenant instanceof Tenant) {
            return $this->createForTenant($tenant, $attributes, $traineeUserId, $traineeUserId);
        }

        return $this->createStandaloneStudent($attributes, $traineeUserId, $traineeUserId);
    }

    public function findInTenant(Tenant $tenant, int $studentUserId): User
    {
        return $tenant->users()
            ->wherePivot('role', Role::STUDENT->value)
            ->where('users.id', $studentUserId)
            ->select('users.*')
            ->firstOrFail();
    }

    public function findVisibleForTenant(Tenant $tenant, int $studentUserId): User
    {
        return $this->visibleStandaloneStudentsForTenantQuery($tenant)
            ->where('users.id', $studentUserId)
            ->firstOrFail();
    }

    public function findForTrainee(?Tenant $tenant, int $traineeUserId, int $studentUserId): User
    {
        return User::query()
            ->join('tenant_student_trainee_links', function ($join) use ($tenant, $traineeUserId): void {
                $join->on('tenant_student_trainee_links.student_user_id', '=', 'users.id')
                    ->where('tenant_student_trainee_links.trainee_user_id', '=', $traineeUserId);

                if ($tenant instanceof Tenant) {
                    $join->where('tenant_student_trainee_links.tenant_id', '=', $tenant->id);
                } else {
                    $join->whereNull('tenant_student_trainee_links.tenant_id');
                }
            })
            ->where('users.profile_type', Role::STUDENT->value)
            ->where('users.id', $studentUserId)
            ->select('users.*')
            ->firstOrFail();
    }

    public function updateForTenant(Tenant $tenant, int $studentUserId, array $attributes, ?int $traineeUserId, ?int $linkedByUserId): User
    {
        return DB::transaction(function () use ($tenant, $studentUserId, $attributes, $traineeUserId, $linkedByUserId): User {
            $student = $this->findInTenant($tenant, $studentUserId);

            $student->fill([
                'name' => trim((string) $attributes['name']),
                'email' => FormPatterns::normalizeEmail((string) $attributes['email']),
                'goal' => $this->nullableString($attributes['goal'] ?? null),
            ]);

            if (! empty($attributes['password'])) {
                $student->password = (string) $attributes['password'];
            }

            $student->save();
            $this->syncStudentTraineeLink($tenant, $student->id, $traineeUserId, $linkedByUserId);

            return $student;
        });
    }

    public function updateVisibleForTenant(Tenant $tenant, int $studentUserId, array $attributes, ?int $traineeUserId, ?int $linkedByUserId): User
    {
        return DB::transaction(function () use ($tenant, $studentUserId, $attributes, $traineeUserId, $linkedByUserId): User {
            $student = $this->findVisibleForTenant($tenant, $studentUserId);

            $student->fill([
                'name' => trim((string) $attributes['name']),
                'email' => FormPatterns::normalizeEmail((string) $attributes['email']),
                'goal' => $this->nullableString($attributes['goal'] ?? null),
            ]);

            if (! empty($attributes['password'])) {
                $student->password = (string) $attributes['password'];
            }

            $student->save();

            if ($traineeUserId !== null) {
                $this->assertTraineeBelongsToTenant($tenant, $traineeUserId);
                $this->syncStudentTraineeLink(null, $student->id, $traineeUserId, $linkedByUserId);
            }

            return $student;
        });
    }

    public function updateForTrainee(?Tenant $tenant, int $traineeUserId, int $studentUserId, array $attributes): User
    {
        $student = $this->findForTrainee($tenant, $traineeUserId, $studentUserId);

        $student->fill([
            'name' => trim((string) $attributes['name']),
            'email' => FormPatterns::normalizeEmail((string) $attributes['email']),
            'goal' => $this->nullableString($attributes['goal'] ?? null),
        ]);

        if (! empty($attributes['password'])) {
            $student->password = (string) $attributes['password'];
        }

        $student->save();

        return $student;
    }

    public function traineeOptionsForTenant(Tenant $tenant): Collection
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

    public function availableStandaloneTrainees(): Collection
    {
        return User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->whereIn('users.profile_type', [Role::TRAINER->value, 'trainee'])
            ->where('users.is_active', true)
            ->orderBy('users.name')
            ->get();
    }

    public function paginateStandaloneTrainees(string $search = '', int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->whereIn('users.profile_type', [Role::TRAINER->value, 'trainee'])
            ->where('users.is_active', true)
            ->orderBy('users.name');

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery->where('users.name', 'like', '%' . $search . '%')
                    ->orWhere('users.email', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function recentForTrainee(?Tenant $tenant, int $traineeUserId, int $limit = 8): Collection
    {
        return User::query()
            ->join('tenant_student_trainee_links', function ($join) use ($tenant, $traineeUserId): void {
                $join->on('tenant_student_trainee_links.student_user_id', '=', 'users.id')
                    ->where('tenant_student_trainee_links.trainee_user_id', '=', $traineeUserId);

                if ($tenant instanceof Tenant) {
                    $join->where('tenant_student_trainee_links.tenant_id', '=', $tenant->id);
                } else {
                    $join->whereNull('tenant_student_trainee_links.tenant_id');
                }
            })
            ->where('users.profile_type', Role::STUDENT->value)
            ->select('users.id', 'users.name', 'users.email', 'users.goal', 'users.created_at')
            ->orderByDesc('users.created_at')
            ->limit($limit)
            ->get();
    }

    public function assignedTraineeForStudent(?Tenant $tenant, int $studentUserId): ?User
    {
        return $this->assignedTraineeForStudentOptional($tenant, $studentUserId);
    }

    public function reassignStudentTrainee(?Tenant $tenant, int $studentUserId, int $traineeUserId, ?int $linkedByUserId): void
    {
        $this->syncStudentTraineeLink($tenant, $studentUserId, $traineeUserId, $linkedByUserId);
    }

    private function assignedTraineeForStudentOptional(?Tenant $tenant, int $studentUserId): ?User
    {
        return User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->join('tenant_student_trainee_links', function ($join) use ($tenant, $studentUserId): void {
                $join->on('tenant_student_trainee_links.trainee_user_id', '=', 'users.id')
                    ->where('tenant_student_trainee_links.student_user_id', '=', $studentUserId);

                if ($tenant instanceof Tenant) {
                    $join->where('tenant_student_trainee_links.tenant_id', '=', $tenant->id);
                } else {
                    $join->whereNull('tenant_student_trainee_links.tenant_id');
                }
            })
            ->first();
    }

    private function visibleStandaloneStudentsForTenantQuery(Tenant $tenant)
    {
        return User::query()
            ->join('tenant_student_trainee_links', function ($join): void {
                $join->on('tenant_student_trainee_links.student_user_id', '=', 'users.id')
                    ->whereNull('tenant_student_trainee_links.tenant_id');
            })
            ->join('tenant_trainee', function ($join) use ($tenant): void {
                $join->on('tenant_trainee.trainee_user_id', '=', 'tenant_student_trainee_links.trainee_user_id')
                    ->where('tenant_trainee.tenant_id', '=', $tenant->id);
            })
            ->where('users.profile_type', Role::STUDENT->value)
            ->select('users.*')
            ->distinct();
    }

    private function createStandaloneStudent(array $attributes, int $traineeUserId, ?int $linkedByUserId): User
    {
        return DB::transaction(function () use ($attributes, $traineeUserId, $linkedByUserId): User {
            $student = User::query()->create([
                'name' => trim((string) $attributes['name']),
                'email' => FormPatterns::normalizeEmail((string) $attributes['email']),
                'password' => (string) $attributes['password'],
                'goal' => $this->nullableString($attributes['goal'] ?? null),
                'birth_date' => $this->nullableString($attributes['birth_date'] ?? null),
                'height' => $this->nullableNumeric($attributes['height'] ?? null),
                'weight' => $this->nullableNumeric($attributes['weight'] ?? null),
                'profile_type' => Role::STUDENT->value,
                'is_active' => true,
                'is_system_admin' => false,
                'credits_balance' => 0,
            ]);

            $this->syncStudentTraineeLink(null, $student->id, $traineeUserId, $linkedByUserId);

            return $student;
        });
    }

    private function assertTraineeBelongsToTenant(Tenant $tenant, int $traineeUserId): void
    {
        $isLinked = DB::table('tenant_trainee')
            ->where('tenant_id', $tenant->id)
            ->where('trainee_user_id', $traineeUserId)
            ->exists();

        abort_unless($isLinked, 422, 'Trainer informado nao pertence a este tenant.');
    }

    private function syncStudentTraineeLink(?Tenant $tenant, int $studentUserId, ?int $traineeUserId, ?int $linkedByUserId): void
    {
        $deleteQuery = TenantStudentTraineeLink::query()
            ->where('student_user_id', $studentUserId);

        if ($tenant instanceof Tenant) {
            $deleteQuery->where('tenant_id', $tenant->id);
        } else {
            $deleteQuery->whereNull('tenant_id');
        }

        $deleteQuery->delete();

        if ($traineeUserId === null) {
            return;
        }

        $isLinkedTrainee = $tenant instanceof Tenant
            ? DB::table('tenant_trainee')
            ->where('tenant_id', $tenant->id)
            ->where('trainee_user_id', $traineeUserId)
            ->exists()
            : User::query()->where('id', $traineeUserId)->whereIn('profile_type', [Role::TRAINER->value, 'trainee'])->exists();

        abort_unless($isLinkedTrainee, 422, 'Trainer informado nao pertence a este tenant.');

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => $tenant?->id,
            'student_user_id' => $studentUserId,
            'trainee_user_id' => $traineeUserId,
            'linked_by_user_id' => $linkedByUserId,
            'note' => null,
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function nullableNumeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
