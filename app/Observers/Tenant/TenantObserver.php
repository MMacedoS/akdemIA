<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Tenant;
use Illuminate\Support\Str;

class TenantObserver
{
    /**
     * Handle the Tenant "creating" event.
     */
    public function creating(Tenant $tenant): void
    {
        if (! filled($tenant->slug) && filled($tenant->name)) {
            $tenant->slug = Str::slug($tenant->name);
        }

        if (! filled($tenant->slug)) {
            return;
        }

        $baseSlug = (string) $tenant->slug;
        $suffix = 1;

        while (Tenant::query()->where('slug', $tenant->slug)->exists()) {
            $tenant->slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
    }

    public function created(Tenant $tenant): void {}

    public function updated(Tenant $tenant): void {}
}
