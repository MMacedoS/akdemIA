<?php

namespace App\Support;

use App\Models\LegalDocument;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LegalDocuments
{
    public static function termsVersion(): string
    {
        return (string) self::documentRecord(LegalDocument::TYPE_TERMS)['version'];
    }

    public static function privacyPolicyVersion(): string
    {
        return (string) self::documentRecord(LegalDocument::TYPE_PRIVACY_POLICY)['version'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function termsDocument(): array
    {
        return self::documentRecord(LegalDocument::TYPE_TERMS);
    }

    /**
     * @return array<string, mixed>
     */
    public static function privacyPolicyDocument(): array
    {
        return self::documentRecord(LegalDocument::TYPE_PRIVACY_POLICY);
    }

    /**
     * @return array<string, string>
     */
    public static function documents(bool $absolute = true): array
    {
        return [
            'terms_url' => route('legal.terms', absolute: $absolute),
            'privacy_policy_url' => route('legal.privacy', absolute: $absolute),
            'terms_version' => self::termsVersion(),
            'privacy_policy_version' => self::privacyPolicyVersion(),
        ];
    }

    /**
     * @return array<string, CarbonInterface|string>
     */
    public static function acceptanceAttributes(?CarbonInterface $acceptedAt = null): array
    {
        $acceptedAt ??= now();

        return [
            'terms_version' => self::termsVersion(),
            'terms_accepted_at' => $acceptedAt,
            'privacy_policy_version' => self::privacyPolicyVersion(),
            'privacy_policy_accepted_at' => $acceptedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function documentRecord(string $type): array
    {
        $fallback = self::fallbackDocument($type);

        if (! self::documentsTableExists()) {
            return $fallback;
        }

        $document = LegalDocument::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->first();

        if ($document === null) {
            return $fallback;
        }

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

    private static function documentsTableExists(): bool
    {
        try {
            return Schema::hasTable('legal_documents');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function fallbackDocument(string $type): array
    {
        $documents = [
            LegalDocument::TYPE_TERMS => [
                'type' => LegalDocument::TYPE_TERMS,
                'title' => 'Termos de Uso',
                'slug' => 'termos-de-uso',
                'version' => (string) config('legal.terms.version'),
                'effective_date' => (string) config('legal.terms.updated_at'),
                'content_html' => (string) config('legal.terms.default_content_html', ''),
                'is_active' => true,
            ],
            LegalDocument::TYPE_PRIVACY_POLICY => [
                'type' => LegalDocument::TYPE_PRIVACY_POLICY,
                'title' => 'Politica de Privacidade',
                'slug' => 'politica-de-privacidade',
                'version' => (string) config('legal.privacy_policy.version'),
                'effective_date' => (string) config('legal.privacy_policy.updated_at'),
                'content_html' => (string) config('legal.privacy_policy.default_content_html', ''),
                'is_active' => true,
            ],
        ];

        return $documents[$type] ?? $documents[LegalDocument::TYPE_TERMS];
    }
}
