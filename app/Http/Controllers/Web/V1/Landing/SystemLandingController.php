<?php

namespace App\Http\Controllers\Web\V1\Landing;

use App\Models\Landing\SystemLandingSetting;
use App\Models\Landing\UserPublicProfile;
use App\Models\Tenant\Tenant;
use Illuminate\View\View;

class SystemLandingController
{
    public function __invoke(): View
    {
        $setting = SystemLandingSetting::query()->first();

        $featuredTenants = Tenant::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'slug']);

        $featuredProfessionals = UserPublicProfile::query()
            ->with('user:id,name,email')
            ->where('is_published', true)
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        return view('landing.system', [
            'setting' => $setting,
            'featuredTenants' => $featuredTenants,
            'featuredProfessionals' => $featuredProfessionals,
        ]);
    }
}
