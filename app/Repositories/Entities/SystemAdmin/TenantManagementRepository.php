<?php

namespace App\Repositories\Entities\SystemAdmin;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Repositories\Contracts\SystemAdmin\TenantManagementRepositoryContract;
use App\Support\FormPatterns;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TenantManagementRepository implements TenantManagementRepositoryContract
{
    public function listAdminCandidates(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function listRecent(int $limit = 24): Collection
    {
        return Tenant::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'contact_email', 'is_active', 'created_at']);
    }

    public function create(string $name, ?string $slug, string $accessEmail, string $defaultPassword): Tenant
    {
        return DB::transaction(function () use ($name, $slug, $accessEmail, $defaultPassword): Tenant {
            $normalizedEmail = FormPatterns::normalizeEmail($accessEmail);

            $tenant = $this->createTenantRecord($name, $slug, $normalizedEmail);

            $accessUser = User::query()->create([
                'name' => trim($name),
                'email' => $normalizedEmail,
                'password' => $defaultPassword,
                'is_active' => true,
                'is_system_admin' => false,
                'credits_balance' => 0,
                'profile_type' => null,
            ]);

            $tenant->users()->syncWithoutDetaching([
                $accessUser->id => ['role' => Role::ADMIN->value],
            ]);

            return $tenant;
        });
    }

    public function createForExistingAdmin(
        User $accessUser,
        string $name,
        ?string $slug,
        ?string $contactEmail = null,
        ?string $contactPhone = null,
        ?string $documentNumber = null,
        ?string $notes = null,
    ): Tenant {
        return DB::transaction(function () use ($accessUser, $name, $slug, $contactEmail, $contactPhone, $documentNumber, $notes): Tenant {
            $tenant = $this->createTenantRecord(
                $name,
                $slug,
                FormPatterns::normalizeEmail($contactEmail) ?? FormPatterns::normalizeEmail($accessUser->email),
                FormPatterns::formatPhone($contactPhone),
                FormPatterns::formatDocument($documentNumber),
                $this->nullableString($notes),
            );

            $tenant->users()->syncWithoutDetaching([
                $accessUser->id => ['role' => Role::ADMIN->value],
            ]);

            return $tenant;
        });
    }

    public function findById(int $id): ?Tenant
    {
        return Tenant::query()->find($id);
    }

    public function update(Tenant $tenant, array $attributes): Tenant
    {
        $rawSlug = trim((string) ($attributes['slug'] ?? $tenant->slug));
        $baseSlug = $rawSlug !== '' ? Str::lower($rawSlug) : Str::slug((string) ($attributes['name'] ?? $tenant->name));
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'tenant';

        $resolvedSlug = $this->ensureUniqueSlugForTenant($baseSlug, $tenant->id);

        $tenant->fill([
            'name' => trim((string) ($attributes['name'] ?? $tenant->name)),
            'slug' => $resolvedSlug,
            'contact_email' => FormPatterns::normalizeEmail($attributes['contact_email'] ?? null),
            'contact_phone' => FormPatterns::formatPhone($this->nullableString($attributes['contact_phone'] ?? null)),
            'document_number' => FormPatterns::formatDocument($this->nullableString($attributes['document_number'] ?? null)),
            'notes' => $this->nullableString($attributes['notes'] ?? null),
            'is_active' => (bool) ($attributes['is_active'] ?? $tenant->is_active),
        ]);
        $tenant->save();

        return $tenant;
    }

    private function ensureUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 1;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $baseSlug . '-' . $suffix;
        }

        return $slug;
    }

    private function createTenantRecord(
        string $name,
        ?string $slug,
        ?string $contactEmail,
        ?string $contactPhone = null,
        ?string $documentNumber = null,
        ?string $notes = null,
    ): Tenant {
        $rawSlug = trim((string) $slug);
        $baseSlug = $rawSlug !== '' ? Str::lower($rawSlug) : Str::slug($name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'tenant';

        return Tenant::query()->create([
            'name' => trim($name),
            'slug' => $this->ensureUniqueSlug($baseSlug),
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'document_number' => $documentNumber,
            'notes' => $notes,
            'is_active' => true,
        ]);
    }

    private function ensureUniqueSlugForTenant(string $baseSlug, int $tenantId): string
    {
        $slug = $baseSlug;
        $suffix = 1;

        while (Tenant::query()->where('slug', $slug)->where('id', '!=', $tenantId)->exists()) {
            $suffix++;
            $slug = $baseSlug . '-' . $suffix;
        }

        return $slug;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
