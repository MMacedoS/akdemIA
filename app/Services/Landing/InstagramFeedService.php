<?php

namespace App\Services\Landing;

use App\Models\Landing\TenantLandingPage;
use Illuminate\Support\Facades\Http;
use Throwable;

class InstagramFeedService
{
    /**
     * @return array{enabled: bool, username: ?string, profile_url: ?string, items: array<int, array<string, mixed>>}
     */
    public function forTenantLanding(?TenantLandingPage $landing): array
    {
        if (! $landing instanceof TenantLandingPage) {
            return $this->emptyFeed();
        }

        $accessToken = trim((string) ($landing->instagram_access_token ?? ''));
        $configuredUsername = trim((string) ($landing->instagram_username ?? ''));

        if ($accessToken === '') {
            return [
                'enabled' => false,
                'username' => $configuredUsername !== '' ? $configuredUsername : null,
                'profile_url' => $configuredUsername !== '' ? 'https://www.instagram.com/' . ltrim($configuredUsername, '@') . '/' : null,
                'items' => [],
            ];
        }

        try {
            $profileResponse = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(8)
                ->get('https://graph.instagram.com/me', [
                    'fields' => 'id,username',
                    'access_token' => $accessToken,
                ]);

            $mediaResponse = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(8)
                ->get('https://graph.instagram.com/me/media', [
                    'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp',
                    'limit' => 6,
                    'access_token' => $accessToken,
                ]);

            if (! $profileResponse->successful() || ! $mediaResponse->successful()) {
                return $this->emptyFeed($configuredUsername);
            }

            $profileData = $profileResponse->json();
            $mediaData = $mediaResponse->json('data');
            $username = trim((string) (data_get($profileData, 'username') ?: $configuredUsername));

            return [
                'enabled' => true,
                'username' => $username !== '' ? $username : null,
                'profile_url' => $username !== '' ? 'https://www.instagram.com/' . ltrim($username, '@') . '/' : null,
                'items' => collect(is_array($mediaData) ? $mediaData : [])
                    ->map(function ($item): array {
                        $mediaType = strtoupper((string) data_get($item, 'media_type', ''));
                        $mediaUrl = (string) (data_get($item, 'media_url') ?: data_get($item, 'thumbnail_url') ?: '');

                        return [
                            'id' => (string) data_get($item, 'id', ''),
                            'caption' => trim((string) data_get($item, 'caption', '')),
                            'media_type' => $mediaType,
                            'media_url' => $mediaUrl,
                            'permalink' => (string) data_get($item, 'permalink', ''),
                            'timestamp' => (string) data_get($item, 'timestamp', ''),
                        ];
                    })
                    ->filter(fn(array $item): bool => $item['media_url'] !== '' || $item['permalink'] !== '')
                    ->values()
                    ->all(),
            ];
        } catch (Throwable) {
            return $this->emptyFeed($configuredUsername);
        }
    }

    /**
     * @return array{enabled: bool, username: ?string, profile_url: ?string, items: array<int, array<string, mixed>>}
     */
    private function emptyFeed(?string $username = null): array
    {
        $normalizedUsername = trim((string) $username);

        return [
            'enabled' => false,
            'username' => $normalizedUsername !== '' ? $normalizedUsername : null,
            'profile_url' => $normalizedUsername !== '' ? 'https://www.instagram.com/' . ltrim($normalizedUsername, '@') . '/' : null,
            'items' => [],
        ];
    }
}
