<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            [
                'type' => LegalDocument::TYPE_TERMS,
                'title' => 'Termos de Uso',
                'slug' => 'termos-de-uso',
                'version' => (string) config('legal.terms.version'),
                'effective_date' => config('legal.terms.updated_at'),
                'content_html' => (string) config('legal.terms.default_content_html', ''),
            ],
            [
                'type' => LegalDocument::TYPE_PRIVACY_POLICY,
                'title' => 'Politica de Privacidade',
                'slug' => 'politica-de-privacidade',
                'version' => (string) config('legal.privacy_policy.version'),
                'effective_date' => config('legal.privacy_policy.updated_at'),
                'content_html' => (string) config('legal.privacy_policy.default_content_html', ''),
            ],
        ];

        foreach ($documents as $document) {
            LegalDocument::query()->updateOrCreate(
                ['type' => $document['type']],
                [
                    'title' => $document['title'],
                    'slug' => $document['slug'],
                    'version' => $document['version'],
                    'effective_date' => $document['effective_date'],
                    'content_html' => $document['content_html'],
                    'is_active' => true,
                ],
            );
        }
    }
}
