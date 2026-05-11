<?php

namespace App\Http\Controllers\Api\V1\Workouts;

use App\Models\Workout\ExerciseMediaCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExerciseMediaController
{
    public function show(string $workoutxName): BinaryFileResponse|JsonResponse
    {
        $normalizedWorkoutxName = trim($workoutxName);

        if ($normalizedWorkoutxName === '' || preg_match('/^[a-z0-9-]+$/', $normalizedWorkoutxName) !== 1) {
            return response()->json([
                'message' => 'Exercise media not found.',
            ], 404);
        }

        $path = $this->resolveStoragePath($normalizedWorkoutxName);

        if ($path === '' || ! $this->exerciseMediaDisk()->exists($path)) {
            $this->migrateLegacyPublicGifToPrivate($path);
        }

        if ($path === '' || ! $this->exerciseMediaDisk()->exists($path)) {
            return response()->json([
                'message' => 'Exercise media not found.',
            ], 404);
        }

        $response = response()->file($this->exerciseMediaDisk()->path($path));
        $response->setPrivate();
        $response->setMaxAge(3600);
        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->headers->set('Content-Type', 'image/gif');

        return $response;
    }

    private function resolveStoragePath(string $workoutxName): string
    {
        $cache = ExerciseMediaCache::query()
            ->where('workoutx_name', $workoutxName)
            ->first();

        $storagePath = trim((string) ($cache?->storage_path ?? ''));

        if ($storagePath !== '') {
            return $storagePath;
        }

        return 'exercises/' . $workoutxName . '.gif';
    }

    private function exerciseMediaDisk()
    {
        return Storage::disk('local');
    }

    private function migrateLegacyPublicGifToPrivate(string $path): void
    {
        if ($path === '') {
            return;
        }

        $publicDisk = Storage::disk('public');

        if (! $publicDisk->exists($path)) {
            return;
        }

        $contents = $publicDisk->get($path);

        if ($contents === false || $contents === '') {
            return;
        }

        $this->exerciseMediaDisk()->put($path, $contents);
        $publicDisk->delete($path);
    }
}
