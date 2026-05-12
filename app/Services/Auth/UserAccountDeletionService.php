<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserAccountDeletionService
{
    public function delete(User $user): void
    {
        if ($user->isSystemAdmin()) {
            abort(403, 'Nao e permitido excluir um usuario system admin.');
        }

        $user->loadMissing(['publicProfile', 'mediaAssets']);

        $storagePaths = $this->userStoragePaths($user);

        DB::transaction(function () use ($user): void {
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $user->id)
                ->delete();

            $user->delete();
        });

        $this->deleteStoragePaths($storagePaths);
    }

    /**
     * @return list<string>
     */
    private function userStoragePaths(User $user): array
    {
        $paths = [
            $this->normalizeStoragePath($user->avatar_path),
            $this->normalizeStoragePath($user->publicProfile?->hero_image_url),
            $this->normalizeStoragePath($user->publicProfile?->hero_video_url),
        ];

        foreach ($user->mediaAssets as $mediaAsset) {
            $paths[] = $this->normalizeStoragePath($mediaAsset->media_url);
        }

        return array_values(array_filter($paths, static fn(?string $path): bool => $path !== null));
    }

    private function normalizeStoragePath(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = trim($value);

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            $path = parse_url($normalized, PHP_URL_PATH);

            if (! is_string($path) || ! str_starts_with($path, '/storage/')) {
                return null;
            }

            $relativePath = Str::after($path, '/storage/');

            return $relativePath !== '' ? $relativePath : null;
        }

        if (str_starts_with($normalized, '/storage/')) {
            $relativePath = Str::after($normalized, '/storage/');

            return $relativePath !== '' ? $relativePath : null;
        }

        return $normalized;
    }

    /**
     * @param array<int, string|null> $paths
     */
    private function deleteStoragePaths(array $paths): void
    {
        $filteredPaths = array_values(array_unique(array_filter($paths, static fn(?string $path): bool => $path !== null && $path !== '')));

        if ($filteredPaths === []) {
            return;
        }

        Storage::disk('public')->delete($filteredPaths);
    }
}
