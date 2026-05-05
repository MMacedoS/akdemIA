<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Models\Landing\SystemLandingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemLandingController extends Controller
{
    public function edit(): View
    {
        $setting = SystemLandingSetting::query()->first();

        return view('web.v1.system_admin.landing.edit', [
            'setting' => $setting,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_description' => ['nullable', 'string', 'max:5000'],
            'hero_image_url' => ['nullable', 'url', 'max:2000'],
            'primary_cta_text' => ['nullable', 'string', 'max:80'],
            'primary_cta_url' => ['nullable', 'url', 'max:2000'],
            'secondary_cta_text' => ['nullable', 'string', 'max:80'],
            'secondary_cta_url' => ['nullable', 'url', 'max:2000'],
            'about_title' => ['nullable', 'string', 'max:160'],
            'about_content' => ['nullable', 'string', 'max:5000'],
            'tenants_section_title' => ['nullable', 'string', 'max:120'],
            'professionals_section_title' => ['nullable', 'string', 'max:120'],
            'differentials_section_title' => ['nullable', 'string', 'max:120'],
            'contact_section_title' => ['nullable', 'string', 'max:120'],
            'contact_description' => ['nullable', 'string', 'max:2000'],
            'contact_email' => ['nullable', 'email:rfc', 'max:190'],
            'contact_whatsapp' => ['nullable', 'string', 'max:40'],
        ]);

        SystemLandingSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'hero_title' => $payload['hero_title'] ?? null,
                'hero_description' => $payload['hero_description'] ?? null,
                'hero_image_url' => $payload['hero_image_url'] ?? null,
                'primary_cta_text' => $payload['primary_cta_text'] ?? null,
                'primary_cta_url' => $payload['primary_cta_url'] ?? null,
                'secondary_cta_text' => $payload['secondary_cta_text'] ?? null,
                'secondary_cta_url' => $payload['secondary_cta_url'] ?? null,
                'about_title' => $payload['about_title'] ?? null,
                'about_content' => $payload['about_content'] ?? null,
                'tenants_section_title' => $payload['tenants_section_title'] ?? null,
                'professionals_section_title' => $payload['professionals_section_title'] ?? null,
                'differentials_section_title' => $payload['differentials_section_title'] ?? null,
                'contact_section_title' => $payload['contact_section_title'] ?? null,
                'contact_description' => $payload['contact_description'] ?? null,
                'contact_email' => $payload['contact_email'] ?? null,
                'contact_whatsapp' => $payload['contact_whatsapp'] ?? null,
            ]
        );

        return redirect()->route('system-admin.landing.edit')
            ->with('status', 'Landing geral do site atualizada com sucesso.');
    }
}
