<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlatformTenantService
{
    public const PLATFORM_TENANT_SLUG = 'plataforma';
    public const PLATFORM_TENANT_NAME = 'Plataforma';

    public function ensurePlatformTenant(): Tenant
    {
        return Tenant::query()->firstOrCreate(
            ['slug' => self::PLATFORM_TENANT_SLUG],
            [
                'name' => self::PLATFORM_TENANT_NAME,
                'is_active' => true,
                'notes' => 'Tenant padrao da plataforma para vinculo inicial de trainees.',
            ]
        );
    }

    public function attachTraineeToPlatform(User $trainee): Tenant
    {
        $tenant = $this->ensurePlatformTenant();

        DB::table('tenant_trainee')->updateOrInsert(
            [
                'tenant_id' => $tenant->id,
                'trainee_user_id' => $trainee->id,
            ],
            [
                'linked_by_user_id' => null,
                'note' => 'Vinculo automatico criado no cadastro da conta.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $tenant;
    }

    public function resolvePreferredTenantForTrainee(User $trainee): ?Tenant
    {
        $platformTenant = $trainee->traineeTenants()
            ->where('tenants.slug', self::PLATFORM_TENANT_SLUG)
            ->where('tenants.is_active', true)
            ->first(['tenants.id', 'tenants.name', 'tenants.slug']);

        if ($platformTenant instanceof Tenant) {
            return $platformTenant;
        }

        $firstLinkedTenant = $trainee->traineeTenants()
            ->where('tenants.is_active', true)
            ->orderBy('tenants.name')
            ->first(['tenants.id', 'tenants.name', 'tenants.slug']);

        if ($firstLinkedTenant instanceof Tenant) {
            return $firstLinkedTenant;
        }

        return $this->attachTraineeToPlatform($trainee);
    }
}
