<?php

namespace App\Repositories\Entities\Tenant;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantStudentTraineeLink;
use App\Models\User;
use App\Repositories\Contracts\Tenant\TraineeStudentRepositoryContract;
use App\Support\LegalDocuments;
use App\Support\FormPatterns;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TraineeStudentRepository implements TraineeStudentRepositoryContract
{
    public function paginateForTenant(Tenant $tenant, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->applySearch($this->tenantStudentsQuery($tenant), $search)
            ->orderBy('users.name');

        return $query->paginate($perPage)->withQueryString();
    }

    public function paginateVisibleForTenant(Tenant $tenant, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->applySearch($this->visibleStandaloneStudentsForTenantQuery($tenant), $search)
            ->orderBy('users.name');

        return $query->paginate($perPage)->withQueryString();
    }

    public function paginateForTrainee(?Tenant $tenant, int $traineeUserId, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->applySearch($this->studentsAssignedToTraineeQuery($tenant, $traineeUserId), $search)
            ->select('users.*')
            ->orderBy('users.name');

        return $query->paginate($perPage)->withQueryString();
    }

    public function metricsForTenant(Tenant $tenant): array
    {
        $baseQuery = $this->tenantStudentsQuery($tenant);

        return [
            'total' => (clone $baseQuery)->count('users.id'),
            'verified' => (clone $baseQuery)->whereNotNull('users.email_verified_at')->count('users.id'),
            'with_goal' => (clone $baseQuery)->whereNotNull('users.goal')->count('users.id'),
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
        $baseQuery = $this->studentsAssignedToTraineeQuery($tenant, $traineeUserId);

        return [
            'total' => (clone $baseQuery)->count('users.id'),
            'verified' => (clone $baseQuery)->whereNotNull('users.email_verified_at')->count('users.id'),
            'with_goal' => (clone $baseQuery)->whereNotNull('users.goal')->count('users.id'),
        ];
    }

    public function createForTenant(Tenant $tenant, array $attributes, ?int $traineeUserId, ?int $linkedByUserId): User
    {
        return DB::transaction(function () use ($tenant, $attributes, $traineeUserId, $linkedByUserId): User {
            $student = $this->createStudent($attributes);

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
        return $this->tenantStudentsQuery($tenant)
            ->where('users.id', $studentUserId)
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
        return $this->studentsAssignedToTraineeQuery($tenant, $traineeUserId)
            ->where('users.id', $studentUserId)
            ->select('users.*')
            ->firstOrFail();
    }

    public function updateForTenant(Tenant $tenant, int $studentUserId, array $attributes, ?int $traineeUserId, ?int $linkedByUserId): User
    {
        return DB::transaction(function () use ($tenant, $studentUserId, $attributes, $traineeUserId, $linkedByUserId): User {
            $student = $this->findInTenant($tenant, $studentUserId);

            $this->updateStudent($student, $attributes);
            $this->syncStudentTraineeLink($tenant, $student->id, $traineeUserId, $linkedByUserId);

            return $student;
        });
    }

    public function updateVisibleForTenant(Tenant $tenant, int $studentUserId, array $attributes, ?int $traineeUserId, ?int $linkedByUserId): User
    {
        return DB::transaction(function () use ($tenant, $studentUserId, $attributes, $traineeUserId, $linkedByUserId): User {
            $student = $this->findVisibleForTenant($tenant, $studentUserId);

            $this->updateStudent($student, $attributes);

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

        $this->updateStudent($student, $attributes);

        return $student;
    }

    public function traineeOptionsForTenant(Tenant $tenant): Collection
    {
        return $this->activeTraineesQuery($tenant)
            ->orderBy('users.name')
            ->get();
    }

    public function availableStandaloneTrainees(): Collection
    {
        return $this->activeTraineesQuery()
            ->orderBy('users.name')
            ->get();
    }

    public function paginateStandaloneTrainees(string $search = '', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->applySearch($this->activeTraineesQuery(), $search)
            ->orderBy('users.name');

        return $query->paginate($perPage)->withQueryString();
    }

    public function recentForTrainee(?Tenant $tenant, int $traineeUserId, int $limit = 8): Collection
    {
        return $this->studentsAssignedToTraineeQuery($tenant, $traineeUserId)
            ->select('users.id', 'users.name', 'users.email', 'users.goal', 'users.created_at')
            ->orderByDesc('users.created_at')
            ->limit($limit)
            ->get();
    }

    public function assignedTraineeForStudent(?Tenant $tenant, int $studentUserId): ?User
    {
        return $this->assignedTraineeQuery($tenant, $studentUserId)->first();
    }

    public function reassignStudentTrainee(?Tenant $tenant, int $studentUserId, int $traineeUserId, ?int $linkedByUserId): void
    {
        $this->syncStudentTraineeLink($tenant, $studentUserId, $traineeUserId, $linkedByUserId);
    }

    private function studentsAssignedToTraineeQuery(?Tenant $tenant, int $traineeUserId)
    {
        return User::query()
            ->join('tenant_student_trainee_links', function ($join) use ($tenant, $traineeUserId): void {
                $join->on('tenant_student_trainee_links.student_user_id', '=', 'users.id')
                    ->where('tenant_student_trainee_links.trainee_user_id', '=', $traineeUserId);

                $this->applyTenantStudentTraineeScope($join, $tenant);
            })
            ->where('users.profile_type', Role::STUDENT->value);
    }

    private function assignedTraineeQuery(?Tenant $tenant, int $studentUserId)
    {
        return User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->join('tenant_student_trainee_links', function ($join) use ($tenant, $studentUserId): void {
                $join->on('tenant_student_trainee_links.trainee_user_id', '=', 'users.id')
                    ->where('tenant_student_trainee_links.student_user_id', '=', $studentUserId);

                $this->applyTenantStudentTraineeScope($join, $tenant);
            });
    }

    private function applyTenantStudentTraineeScope($query, ?Tenant $tenant): void
    {
        if ($tenant instanceof Tenant) {
            $query->where('tenant_student_trainee_links.tenant_id', '=', $tenant->id);

            return;
        }

        $query->whereNull('tenant_student_trainee_links.tenant_id');
    }

    private function tenantStudentsQuery(Tenant $tenant)
    {
        return $tenant->users()
            ->wherePivot('role', Role::STUDENT->value)
            ->select('users.*');
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

    private function activeTraineesQuery(?Tenant $tenant = null)
    {
        $query = User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->whereIn('users.profile_type', [Role::TRAINER->value, 'trainee'])
            ->where('users.is_active', true);

        if (! $tenant instanceof Tenant) {
            return $query;
        }

        return $query->join('tenant_trainee', function ($join) use ($tenant): void {
            $join->on('tenant_trainee.trainee_user_id', '=', 'users.id')
                ->where('tenant_trainee.tenant_id', '=', $tenant->id);
        });
    }

    private function applySearch($query, string $search)
    {
        if ($search === '') {
            return $query;
        }

        return $query->where(function ($innerQuery) use ($search): void {
            $innerQuery->where('users.name', 'like', '%' . $search . '%')
                ->orWhere('users.email', 'like', '%' . $search . '%');
        });
    }

    private function createStandaloneStudent(array $attributes, int $traineeUserId, ?int $linkedByUserId): User
    {
        return DB::transaction(function () use ($attributes, $traineeUserId, $linkedByUserId): User {
            $student = $this->createStudent($attributes);

            $this->syncStudentTraineeLink(null, $student->id, $traineeUserId, $linkedByUserId);

            return $student;
        });
    }

    private function createStudent(array $attributes): User
    {
        return User::query()->create([
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
            ...$this->policyAcceptanceAttributes($attributes),
        ]);
    }

    private function updateStudent(User $student, array $attributes): void
    {
        $student->fill([
            'name' => trim((string) $attributes['name']),
            'email' => FormPatterns::normalizeEmail((string) $attributes['email']),
            'goal' => $this->nullableString($attributes['goal'] ?? null),
        ]);

        if (! empty($attributes['password'])) {
            $student->password = (string) $attributes['password'];
        }

        $student->save();
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
        $this->studentTraineeLinkQuery($tenant, $studentUserId)->delete();

        if ($traineeUserId === null) {
            return;
        }

        abort_unless($this->isValidTraineeForContext($tenant, $traineeUserId), 422, 'Trainer informado nao pertence a este tenant.');

        TenantStudentTraineeLink::query()->create([
            'tenant_id' => $tenant?->id,
            'student_user_id' => $studentUserId,
            'trainee_user_id' => $traineeUserId,
            'linked_by_user_id' => $linkedByUserId,
            'note' => null,
        ]);
    }

    private function studentTraineeLinkQuery(?Tenant $tenant, int $studentUserId)
    {
        $query = TenantStudentTraineeLink::query()
            ->where('student_user_id', $studentUserId);

        if ($tenant instanceof Tenant) {
            return $query->where('tenant_id', $tenant->id);
        }

        return $query->whereNull('tenant_id');
    }

    private function isValidTraineeForContext(?Tenant $tenant, int $traineeUserId): bool
    {
        if ($tenant instanceof Tenant) {
            return DB::table('tenant_trainee')
                ->where('tenant_id', $tenant->id)
                ->where('trainee_user_id', $traineeUserId)
                ->exists();
        }

        return User::query()
            ->where('id', $traineeUserId)
            ->whereIn('profile_type', [Role::TRAINER->value, 'trainee'])
            ->exists();
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

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function policyAcceptanceAttributes(array $attributes): array
    {
        if (($attributes['terms_of_use'] ?? false) && ($attributes['privacy_policy'] ?? false)) {
            return LegalDocuments::acceptanceAttributes();
        }

        return [];
    }
}
