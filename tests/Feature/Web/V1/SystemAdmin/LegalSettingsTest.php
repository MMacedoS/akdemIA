<?php

namespace Tests\Feature\Web\V1\SystemAdmin;

use App\Enums\Role;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_update_legal_documents(): void
    {
        $user = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
            'is_system_admin' => true,
        ]);

        $response = $this->actingAs($user)
            ->put(route('system-admin.settings.legal.update'), [
                'terms_title' => 'Termos oficiais',
                'terms_version' => '2026-05-09',
                'terms_effective_date' => '2026-05-09',
                'terms_content_html' => '<h2>Termos</h2><p>Conteudo oficial.</p>',
                'privacy_title' => 'Politica oficial',
                'privacy_version' => '2026-05-10',
                'privacy_effective_date' => '2026-05-10',
                'privacy_content_html' => '<h2>Privacidade</h2><p>Conteudo oficial.</p>',
            ]);

        $response->assertRedirect(route('system-admin.settings.legal.edit'));

        $this->assertDatabaseHas('legal_documents', [
            'type' => LegalDocument::TYPE_TERMS,
            'title' => 'Termos oficiais',
            'version' => '2026-05-09',
        ]);

        $this->assertDatabaseHas('legal_documents', [
            'type' => LegalDocument::TYPE_PRIVACY_POLICY,
            'title' => 'Politica oficial',
            'version' => '2026-05-10',
        ]);
    }
}
