<?php

namespace App\Support;

use Carbon\CarbonInterface;

class LegalDocuments
{
    public static function termsVersion(): string
    {
        return (string) config('legal.terms.version');
    }

    public static function privacyPolicyVersion(): string
    {
        return (string) config('legal.privacy_policy.version');
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
}
