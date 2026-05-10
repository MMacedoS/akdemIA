<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDocumentPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_page_is_publicly_available(): void
    {
        $response = $this->get('/termos-de-uso');

        $response->assertOk()
            ->assertSee('Termos', false);
    }

    public function test_privacy_policy_page_is_publicly_available(): void
    {
        $response = $this->get('/politica-de-privacidade');

        $response->assertOk()
            ->assertSee('Privacidade', false);
    }
}