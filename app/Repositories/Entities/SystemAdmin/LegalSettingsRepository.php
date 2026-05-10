<?php

namespace App\Repositories\Entities\SystemAdmin;

use App\Models\LegalDocument;
use App\Repositories\Contracts\SystemAdmin\LegalSettingsRepositoryContract;
use App\Support\LegalDocuments;
use Illuminate\Support\Collection;

class LegalSettingsRepository implements LegalSettingsRepositoryContract
{
    public function values(): Collection
    {
        $documents = LegalDocument::query()
            ->whereIn('type', [
                LegalDocument::TYPE_TERMS,
                LegalDocument::TYPE_PRIVACY_POLICY,
            ])
            ->get()
            ->keyBy('type')
            ->map(fn(LegalDocument $document) => $this->toArray($document));

        return collect([
            LegalDocument::TYPE_TERMS => $documents->get(LegalDocument::TYPE_TERMS, LegalDocuments::termsDocument()),
            LegalDocument::TYPE_PRIVACY_POLICY => $documents->get(LegalDocument::TYPE_PRIVACY_POLICY, LegalDocuments::privacyPolicyDocument()),
        ]);
    }

    public function update(array $documents): void
    {
        foreach ($documents as $type => $payload) {
            LegalDocument::query()->updateOrCreate(
                ['type' => $type],
                [
                    'title' => $payload['title'],
                    'slug' => $payload['slug'],
                    'version' => $payload['version'],
                    'effective_date' => $payload['effective_date'],
                    'content_html' => $payload['content_html'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(LegalDocument $document): array
    {
        return [
            'type' => $document->type,
            'title' => $document->title,
            'slug' => $document->slug,
            'version' => $document->version,
            'effective_date' => optional($document->effective_date)->toDateString(),
            'content_html' => $document->content_html,
            'is_active' => (bool) $document->is_active,
        ];
    }
}
