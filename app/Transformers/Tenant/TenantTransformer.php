<?php

namespace App\Transformers\Tenant;

use App\Models\Tenant\Tenant;
use Illuminate\Support\Collection;

class TenantTransformer
{
    /**
     * @return array{id:int,name:string,slug:string}
     */
    public function transform(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
        ];
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return array<int, array{id:int,name:string,slug:string}>
     */
    public function transformCollection(Collection $tenants): array
    {
        return $tenants
            ->map(fn(Tenant $tenant) => $this->transform($tenant))
            ->values()
            ->all();
    }
}
