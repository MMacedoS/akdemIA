<?php

namespace App\Services\System;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantCascadeDeletionService
{
    /**
     * @return array<string, int>
     */
    public function delete(Tenant $tenant): array
    {
        $storagePaths = [];

        $summary = DB::transaction(function () use ($tenant, &$storagePaths): array {
            $tenant->loadMissing(['landingPage', 'professionalMedia']);

            $storagePaths = [
                ...$this->tenantStoragePaths($tenant),
            ];

            $accessUsers = DB::table('tenant_user')
                ->where('tenant_id', $tenant->id)
                ->get(['user_id', 'role']);

            $tenantAdminIds = $accessUsers
                ->where('role', Role::ADMIN->value)
                ->pluck('user_id')
                ->map(static fn(mixed $id): int => (int) $id)
                ->all();

            $trainerIds = $accessUsers
                ->where('role', Role::TRAINER->value)
                ->pluck('user_id')
                ->map(static fn(mixed $id): int => (int) $id)
                ->all();

            $studentIds = $accessUsers
                ->where('role', Role::STUDENT->value)
                ->pluck('user_id')
                ->map(static fn(mixed $id): int => (int) $id)
                ->all();

            $accessUserIds = $accessUsers
                ->pluck('user_id')
                ->map(static fn(mixed $id): int => (int) $id)
                ->all();

            $traineeIds = $tenant->trainees()
                ->pluck('users.id')
                ->map(static fn(mixed $id): int => (int) $id)
                ->all();

            $candidateUserIds = array_values(array_unique([
                ...$accessUserIds,
                ...$studentIds,
                ...$traineeIds,
            ]));

            $deletedAccessUsers = 0;
            $deletedTenantAdmins = 0;
            $deletedTrainerUsers = 0;
            $deletedStudentUsers = 0;
            $deletedTraineeUsers = 0;
            $preservedSystemAdmins = 0;

            if ($candidateUserIds !== []) {
                $users = User::query()
                    ->whereIn('id', $candidateUserIds)
                    ->with(['publicProfile', 'mediaAssets'])
                    ->get();

                foreach ($users as $user) {
                    if ($user->isSystemAdmin()) {
                        $preservedSystemAdmins++;
                        continue;
                    }

                    $storagePaths = [
                        ...$storagePaths,
                        ...$this->userStoragePaths($user),
                    ];

                    $isAccessUser = in_array($user->id, $accessUserIds, true);
                    $isTenantAdmin = in_array($user->id, $tenantAdminIds, true);
                    $isTrainer = in_array($user->id, $trainerIds, true);
                    $isStudent = in_array($user->id, $studentIds, true);
                    $isTrainee = in_array($user->id, $traineeIds, true);

                    $user->delete();

                    if ($isAccessUser) {
                        $deletedAccessUsers++;
                    }

                    if ($isTenantAdmin) {
                        $deletedTenantAdmins++;
                    }

                    if ($isTrainer) {
                        $deletedTrainerUsers++;
                    }

                    if ($isStudent) {
                        $deletedStudentUsers++;
                    }

                    if ($isTrainee) {
                        $deletedTraineeUsers++;
                    }
                }
            }

            $deletedAiLogs = DB::table('ai_logs')
                ->where('tenant_id', $tenant->id)
                ->delete();

            $deletedCreditTransactions = DB::table('credit_transactions')
                ->where('tenant_id', $tenant->id)
                ->delete();

            $deletedCreditRequests = DB::table('credit_requests')
                ->where('tenant_id', $tenant->id)
                ->delete();

            $deletedProfessionalMedia = $tenant->professionalMedia()->count();
            $deletedLandingPages = $tenant->landingPage()->exists() ? 1 : 0;

            $tenant->delete();

            return [
                'deleted_access_users' => $deletedAccessUsers,
                'deleted_tenant_admin_users' => $deletedTenantAdmins,
                'deleted_trainer_users' => $deletedTrainerUsers,
                'deleted_student_users' => $deletedStudentUsers,
                'deleted_trainee_users' => $deletedTraineeUsers,
                'preserved_system_admin_users' => $preservedSystemAdmins,
                'deleted_professional_media' => $deletedProfessionalMedia,
                'deleted_landing_pages' => $deletedLandingPages,
                'deleted_ai_logs' => $deletedAiLogs,
                'deleted_credit_requests' => $deletedCreditRequests,
                'deleted_credit_transactions' => $deletedCreditTransactions,
            ];
        });

        $this->deleteStoragePaths($storagePaths);

        return $summary;
    }

    /**
     * @return list<string>
     */
    private function tenantStoragePaths(Tenant $tenant): array
    {
        $paths = [
            $this->normalizeStoragePath($tenant->landingPage?->hero_image_url),
            $this->normalizeStoragePath($tenant->landingPage?->hero_video_url),
        ];

        foreach ($tenant->professionalMedia as $media) {
            $paths[] = $this->normalizeStoragePath($media->media_url);
        }

        return array_values(array_filter($paths, static fn(?string $path): bool => $path !== null));
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
