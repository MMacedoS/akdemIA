<?php

namespace App\Services\Workouts;

use App\Models\Workout\ExerciseMediaCache;
use App\Support\Workout\ExerciseAssetBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WorkoutMediaService
{
    public function __construct(
        private readonly ?ExerciseAssetBuilder $assetBuilder = null,
    ) {}

    public function enrichWorkoutPlan(array $workoutPlan): array
    {
        $weeklyPlan = data_get($workoutPlan, 'weekly_plan', []);

        if (! is_array($weeklyPlan) || $weeklyPlan === []) {
            return $workoutPlan;
        }

        $isEnabled = $this->isEnabled();

        $normalizedPlan = collect($weeklyPlan)
            ->map(function ($dayPlan) use ($isEnabled) {
                if (! is_array($dayPlan)) {
                    return $dayPlan;
                }

                $exercises = data_get($dayPlan, 'exercises', []);

                if (! is_array($exercises)) {
                    return $dayPlan;
                }

                data_set($dayPlan, 'exercises', collect($exercises)
                    ->map(function ($exercise) use ($isEnabled) {
                        if (! is_array($exercise)) {
                            return $exercise;
                        }

                        $name = trim((string) data_get($exercise, 'name', 'Exercicio'));
                        $steps = $this->assetBuilder()->normalizeSteps(
                            data_get($exercise, 'steps'),
                            $name,
                            trim((string) data_get($exercise, 'notes', '')),
                        );

                        data_set($exercise, 'steps', $steps);

                        $catalogExercise = $this->resolveCatalogExerciseForPlanExercise($exercise);

                        $displayName = $this->resolveExerciseDisplayName($exercise, $catalogExercise);

                        $workoutxName = $catalogExercise?->workoutx_name
                            ?: $this->normalizeWorkoutxName(
                                data_get($exercise, 'workoutx_name', data_get($exercise, 'workoutx_lookup.name', '')),
                                $name,
                            );

                        $remoteExerciseId = trim((string) ($catalogExercise?->remote_exercise_id ?: data_get($exercise, 'remote_exercise_id', '')));

                        data_set($exercise, 'name', $displayName);
                        data_set($exercise, 'workoutx_name', $workoutxName);
                        data_set($exercise, 'remote_exercise_id', $remoteExerciseId);

                        $media = $catalogExercise instanceof ExerciseMediaCache
                            ? $this->resolveCachedMedia($catalogExercise)
                            : $this->resolveLocalGif($workoutxName, $isEnabled);

                        data_set($exercise, 'exercise_media_path', $media['path']);
                        data_set($exercise, 'exercise_media_url', $media['url']);

                        data_set($exercise, 'illustration_svg', '');

                        return $exercise;
                    })
                    ->all());

                return $dayPlan;
            })
            ->all();

        $workoutPlan['weekly_plan'] = $normalizedPlan;

        return $workoutPlan;
    }

    public function workoutPlanNeedsMediaRefresh(array $workoutPlan): bool
    {
        $weeklyPlan = data_get($workoutPlan, 'weekly_plan', []);

        if (! is_array($weeklyPlan) || $weeklyPlan === []) {
            return false;
        }

        $isEnabled = $this->isEnabled();
        foreach ($weeklyPlan as $dayPlan) {
            $exercises = data_get($dayPlan, 'exercises', []);

            if (! is_array($exercises)) {
                continue;
            }

            foreach ($exercises as $exercise) {
                if (! is_array($exercise)) {
                    continue;
                }

                $steps = data_get($exercise, 'steps', []);
                if (! is_array($steps) || $steps === []) {
                    return true;
                }

                $workoutxName = trim((string) data_get($exercise, 'workoutx_name', data_get($exercise, 'workoutx_lookup.name', '')));
                if ($workoutxName === '') {
                    return true;
                }

                if ($isEnabled && trim((string) data_get($exercise, 'remote_exercise_id', '')) === '') {
                    $catalogExercise = $this->resolveCatalogExerciseForPlanExercise($exercise);

                    if ($catalogExercise instanceof ExerciseMediaCache && trim((string) $catalogExercise->remote_exercise_id) !== '') {
                        return true;
                    }
                }

                if (! $isEnabled) {
                    continue;
                }

                $hasMedia = trim((string) data_get($exercise, 'exercise_media_url', '')) !== '';

                if (! $hasMedia) {
                    return true;
                }
            }
        }

        return false;
    }

    public function normalizeWorkoutxName(mixed $value, string $fallbackName = ''): string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            $normalized = $fallbackName;
        }

        $normalized = mb_strtolower($normalized);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);

        if (is_string($transliterated) && trim($transliterated) !== '') {
            $normalized = $transliterated;
        }

        $normalized = preg_replace('/[^a-z0-9\s-]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[-\s]+/', '-', trim($normalized)) ?? trim($normalized);
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'body-weight-exercise';
    }

    public function lookupExerciseByName(string $name): array
    {
        $queryName = trim($name);

        if ($queryName === '') {
            return [
                'found' => false,
                'cached' => false,
                'query' => '',
                'workoutx_name' => '',
                'exercise' => null,
                'media' => [
                    'path' => '',
                    'url' => '',
                ],
            ];
        }

        $workoutxName = $this->normalizeWorkoutxName($queryName, $queryName);
        $cache = ExerciseMediaCache::query()
            ->where('workoutx_name', $workoutxName)
            ->first();

        if ($cache instanceof ExerciseMediaCache) {
            return $this->buildLookupResponse($cache, true);
        }

        if (! $this->isEnabled()) {
            return [
                'found' => false,
                'cached' => false,
                'query' => $queryName,
                'workoutx_name' => $workoutxName,
                'exercise' => null,
                'media' => [
                    'path' => '',
                    'url' => '',
                ],
            ];
        }

        $payload = $this->fetchExercisePayload($workoutxName);
        $exercise = $this->extractExerciseData($payload);

        if (! is_array($exercise)) {
            return [
                'found' => false,
                'cached' => false,
                'query' => $queryName,
                'workoutx_name' => $workoutxName,
                'exercise' => null,
                'media' => [
                    'path' => '',
                    'url' => '',
                ],
            ];
        }

        $gifUrl = trim((string) data_get($exercise, 'gifUrl', ''));
        $media = $gifUrl !== ''
            ? $this->storeGifFromUrl($workoutxName, $gifUrl)
            : ['path' => '', 'url' => ''];

        $cache = ExerciseMediaCache::query()->updateOrCreate(
            [
                'workoutx_name' => $workoutxName,
            ],
            [
                'remote_exercise_id' => trim((string) data_get($exercise, 'id', '')) ?: null,
                'query_name' => $queryName,
                'remote_gif_url' => $gifUrl !== '' ? $gifUrl : null,
                'storage_path' => $media['path'] !== '' ? $media['path'] : null,
                'payload' => $exercise,
            ]
        );

        return $this->buildLookupResponse($cache, false, $media);
    }

    public function catalogStats(): array
    {
        return [
            'total' => ExerciseMediaCache::query()->count(),
            'with_remote_id' => ExerciseMediaCache::query()->whereNotNull('remote_exercise_id')->count(),
            'last_synced_at' => ExerciseMediaCache::query()->max('updated_at'),
        ];
    }

    public function catalogGifStats(): array
    {
        $baseQuery = ExerciseMediaCache::query()
            ->whereNotNull('remote_exercise_id')
            ->whereNotNull('remote_gif_url')
            ->where('remote_gif_url', '!=', '');

        return [
            'with_remote_gif_url' => (clone $baseQuery)->count(),
            'saved_local' => (clone $baseQuery)
                ->whereNotNull('storage_path')
                ->where('storage_path', '!=', '')
                ->count(),
            'pending_local_file' => $this->pendingCatalogGifsQuery()->count(),
        ];
    }

    public function syncExerciseCatalog(): array
    {
        $this->assertCatalogSyncConfigured();

        $limit = $this->resolveCatalogRequestLimit();
        $offset = 0;
        $synced = 0;
        $created = 0;
        $updated = 0;

        do {
            $pageResult = $this->syncExerciseCatalogPage($offset, $limit);

            $pageCount = $pageResult['page_count'];
            $synced += $pageResult['synced'];
            $created += $pageResult['created'];
            $updated += $pageResult['updated'];
            $offset += $pageCount;
        } while ($pageCount === $limit && $pageCount > 0);

        return [
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
            'unchanged' => max($synced - $created - $updated, 0),
            'total_cached' => ExerciseMediaCache::query()->count(),
        ];
    }

    public function assertCatalogSyncConfigured(): void
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Ative a integracao WorkoutX antes de sincronizar o catalogo.');
        }

        if ($this->workoutxApiBaseUrl() === '') {
            throw new RuntimeException('Defina a API Base URL da WorkoutX antes de sincronizar o catalogo.');
        }

        if ($this->workoutxApiKey() === '') {
            throw new RuntimeException('Defina a API Key da WorkoutX antes de sincronizar o catalogo.');
        }
    }

    public function syncExerciseCatalogPage(int $offset, ?int $limit = null): array
    {
        $this->assertCatalogSyncConfigured();

        $safeOffset = max(0, $offset);
        $safeLimit = max(1, $limit ?? $this->resolveCatalogRequestLimit());

        $payload = $this->requestWorkoutxJson('/exercises', [
            'limit' => $safeLimit,
            'offset' => $safeOffset,
        ]);

        if (! is_array($payload)) {
            throw new RuntimeException(
                $safeOffset === 0
                    ? 'Nao foi possivel consultar o catalogo da WorkoutX. Confira a configuracao e tente novamente.'
                    : "A sincronizacao foi interrompida a partir do offset {$safeOffset}."
            );
        }

        $exercises = $this->extractExerciseCollection($payload);
        $pageCount = count($exercises);
        $synced = 0;
        $created = 0;
        $updated = 0;

        foreach ($exercises as $exercise) {
            $result = $this->upsertCatalogExercise($exercise);

            if ($result === null) {
                continue;
            }

            $synced++;

            if ($result === 'created') {
                $created++;
            }

            if ($result === 'updated') {
                $updated++;
            }
        }

        return [
            'offset' => $safeOffset,
            'limit' => $safeLimit,
            'page_count' => $pageCount,
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
            'unchanged' => max($synced - $created - $updated, 0),
            'has_more' => $pageCount === $safeLimit && $pageCount > 0,
            'next_offset' => $safeOffset + $pageCount,
            'total_cached' => ExerciseMediaCache::query()->count(),
        ];
    }

    public function workoutxSyncStatus(): array
    {
        $status = Cache::get($this->workoutxSyncStatusCacheKey(), []);

        return [
            'state' => (string) data_get($status, 'state', 'idle'),
            'started_at' => data_get($status, 'started_at'),
            'finished_at' => data_get($status, 'finished_at'),
            'message' => (string) data_get($status, 'message', ''),
            'requested_by_user_id' => data_get($status, 'requested_by_user_id'),
            'progress' => [
                'synced' => (int) data_get($status, 'progress.synced', 0),
                'created' => (int) data_get($status, 'progress.created', 0),
                'updated' => (int) data_get($status, 'progress.updated', 0),
                'unchanged' => (int) data_get($status, 'progress.unchanged', 0),
                'next_offset' => (int) data_get($status, 'progress.next_offset', 0),
                'total_cached' => (int) data_get($status, 'progress.total_cached', 0),
            ],
        ];
    }

    public function startWorkoutxSyncStatus(?int $requestedByUserId = null): void
    {
        Cache::forever($this->workoutxSyncStatusCacheKey(), [
            'state' => 'queued',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'requested_by_user_id' => $requestedByUserId,
            'message' => 'Sincronizacao enfileirada e aguardando processamento.',
            'progress' => [
                'synced' => 0,
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'next_offset' => 0,
                'total_cached' => ExerciseMediaCache::query()->count(),
            ],
        ]);
    }

    public function markWorkoutxSyncRunning(int $offset): void
    {
        $status = $this->workoutxSyncStatus();

        Cache::forever($this->workoutxSyncStatusCacheKey(), [
            'state' => 'running',
            'started_at' => $status['started_at'] ?: now()->toIso8601String(),
            'finished_at' => null,
            'requested_by_user_id' => $status['requested_by_user_id'],
            'message' => 'Sincronizacao em andamento com intervalo entre paginas para respeitar o limite da API.',
            'progress' => array_merge($status['progress'], [
                'next_offset' => max(0, $offset),
            ]),
        ]);
    }

    public function advanceWorkoutxSyncStatus(array $pageResult): array
    {
        $status = $this->workoutxSyncStatus();
        $progress = $status['progress'];

        $updatedProgress = [
            'synced' => $progress['synced'] + (int) ($pageResult['synced'] ?? 0),
            'created' => $progress['created'] + (int) ($pageResult['created'] ?? 0),
            'updated' => $progress['updated'] + (int) ($pageResult['updated'] ?? 0),
            'unchanged' => $progress['unchanged'] + (int) ($pageResult['unchanged'] ?? 0),
            'next_offset' => (int) ($pageResult['next_offset'] ?? $progress['next_offset']),
            'total_cached' => (int) ($pageResult['total_cached'] ?? $progress['total_cached']),
        ];

        Cache::forever($this->workoutxSyncStatusCacheKey(), [
            'state' => 'running',
            'started_at' => $status['started_at'] ?: now()->toIso8601String(),
            'finished_at' => null,
            'requested_by_user_id' => $status['requested_by_user_id'],
            'message' => 'Sincronizacao em andamento com intervalo entre paginas para respeitar o limite da API.',
            'progress' => $updatedProgress,
        ]);

        return $updatedProgress;
    }

    public function completeWorkoutxSyncStatus(array $progress): void
    {
        Cache::forever($this->workoutxSyncStatusCacheKey(), [
            'state' => 'completed',
            'started_at' => data_get($this->workoutxSyncStatus(), 'started_at', now()->toIso8601String()),
            'finished_at' => now()->toIso8601String(),
            'requested_by_user_id' => data_get($this->workoutxSyncStatus(), 'requested_by_user_id'),
            'message' => sprintf(
                'Sincronizacao concluida. %d processados, %d novos, %d atualizados, %d sem alteracao.',
                (int) ($progress['synced'] ?? 0),
                (int) ($progress['created'] ?? 0),
                (int) ($progress['updated'] ?? 0),
                (int) ($progress['unchanged'] ?? 0),
            ),
            'progress' => $progress,
        ]);

        $cacheKey = 'workoutx:cache_buster';

        if (Cache::has($cacheKey)) {
            Cache::increment($cacheKey);

            return;
        }

        Cache::forever($cacheKey, 1);
    }

    public function workoutxGifSyncStatus(): array
    {
        $status = Cache::get($this->workoutxGifSyncStatusCacheKey(), []);

        return [
            'state' => (string) data_get($status, 'state', 'idle'),
            'started_at' => data_get($status, 'started_at'),
            'finished_at' => data_get($status, 'finished_at'),
            'message' => (string) data_get($status, 'message', ''),
            'requested_by_user_id' => data_get($status, 'requested_by_user_id'),
            'progress' => [
                'processed' => (int) data_get($status, 'progress.processed', 0),
                'downloaded' => (int) data_get($status, 'progress.downloaded', 0),
                'failed' => (int) data_get($status, 'progress.failed', 0),
                'pending_local_file' => (int) data_get($status, 'progress.pending_local_file', 0),
                'next_remote_exercise_id' => (string) data_get($status, 'progress.next_remote_exercise_id', ''),
            ],
        ];
    }

    public function startWorkoutxGifSyncStatus(?int $requestedByUserId = null): void
    {
        Cache::forever($this->workoutxGifSyncStatusCacheKey(), [
            'state' => 'queued',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'requested_by_user_id' => $requestedByUserId,
            'message' => 'Download dos GIFs pendentes enfileirado e aguardando processamento.',
            'progress' => [
                'processed' => 0,
                'downloaded' => 0,
                'failed' => 0,
                'pending_local_file' => $this->pendingCatalogGifsQuery()->count(),
                'next_remote_exercise_id' => '',
            ],
        ]);
    }

    public function markWorkoutxGifSyncRunning(?string $afterRemoteExerciseId = null): void
    {
        $status = $this->workoutxGifSyncStatus();

        Cache::forever($this->workoutxGifSyncStatusCacheKey(), [
            'state' => 'running',
            'started_at' => $status['started_at'] ?: now()->toIso8601String(),
            'finished_at' => null,
            'requested_by_user_id' => $status['requested_by_user_id'],
            'message' => 'Download dos GIFs pendentes em andamento.',
            'progress' => array_merge($status['progress'], [
                'next_remote_exercise_id' => trim((string) $afterRemoteExerciseId),
                'pending_local_file' => $this->pendingCatalogGifsQuery()->count(),
            ]),
        ]);
    }

    public function advanceWorkoutxGifSyncStatus(array $batchResult): array
    {
        $status = $this->workoutxGifSyncStatus();
        $progress = $status['progress'];

        $updatedProgress = [
            'processed' => $progress['processed'] + (int) ($batchResult['processed'] ?? 0),
            'downloaded' => $progress['downloaded'] + (int) ($batchResult['downloaded'] ?? 0),
            'failed' => $progress['failed'] + (int) ($batchResult['failed'] ?? 0),
            'pending_local_file' => (int) ($batchResult['pending_local_file'] ?? $progress['pending_local_file']),
            'next_remote_exercise_id' => (string) ($batchResult['next_remote_exercise_id'] ?? $progress['next_remote_exercise_id']),
        ];

        Cache::forever($this->workoutxGifSyncStatusCacheKey(), [
            'state' => 'running',
            'started_at' => $status['started_at'] ?: now()->toIso8601String(),
            'finished_at' => null,
            'requested_by_user_id' => $status['requested_by_user_id'],
            'message' => 'Download dos GIFs pendentes em andamento.',
            'progress' => $updatedProgress,
        ]);

        return $updatedProgress;
    }

    public function completeWorkoutxGifSyncStatus(array $progress): void
    {
        Cache::forever($this->workoutxGifSyncStatusCacheKey(), [
            'state' => 'completed',
            'started_at' => data_get($this->workoutxGifSyncStatus(), 'started_at', now()->toIso8601String()),
            'finished_at' => now()->toIso8601String(),
            'requested_by_user_id' => data_get($this->workoutxGifSyncStatus(), 'requested_by_user_id'),
            'message' => sprintf(
                'Download de GIFs concluido. %d processados, %d baixados, %d falhas, %d pendentes.',
                (int) ($progress['processed'] ?? 0),
                (int) ($progress['downloaded'] ?? 0),
                (int) ($progress['failed'] ?? 0),
                (int) ($progress['pending_local_file'] ?? 0),
            ),
            'progress' => $progress,
        ]);
    }

    public function failWorkoutxGifSyncStatus(string $message): void
    {
        $status = $this->workoutxGifSyncStatus();

        Cache::forever($this->workoutxGifSyncStatusCacheKey(), [
            'state' => 'failed',
            'started_at' => $status['started_at'] ?: now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'requested_by_user_id' => $status['requested_by_user_id'],
            'message' => $message,
            'progress' => array_merge($status['progress'], [
                'pending_local_file' => $this->pendingCatalogGifsQuery()->count(),
            ]),
        ]);
    }

    public function syncPendingCatalogGifs(?string $afterRemoteExerciseId = null, ?int $limit = null): array
    {
        $safeLimit = max(1, min((int) ($limit ?? $this->resolveCatalogRequestLimit()), 100));
        $safeCursor = trim((string) $afterRemoteExerciseId);

        $query = $this->pendingCatalogGifsQuery();

        if ($safeCursor !== '') {
            $query->where('remote_exercise_id', '>', $safeCursor);
        }

        $batch = $query
            ->orderBy('remote_exercise_id')
            ->limit($safeLimit)
            ->get();

        $processed = 0;
        $downloaded = 0;
        $failed = 0;
        $lastRemoteExerciseId = $safeCursor;

        foreach ($batch as $exercise) {
            $processed++;
            $lastRemoteExerciseId = (string) ($exercise->remote_exercise_id ?? $lastRemoteExerciseId);

            if ($this->persistCatalogExerciseGif($exercise)) {
                $downloaded++;
                continue;
            }

            $failed++;
        }

        $hasMore = $batch->count() === $safeLimit
            && $this->pendingCatalogGifsQuery()
            ->where('remote_exercise_id', '>', $lastRemoteExerciseId)
            ->exists();

        return [
            'processed' => $processed,
            'downloaded' => $downloaded,
            'failed' => $failed,
            'pending_local_file' => $this->pendingCatalogGifsQuery()->count(),
            'next_remote_exercise_id' => $lastRemoteExerciseId,
            'has_more' => $hasMore,
            'limit' => $safeLimit,
        ];
    }

    public function persistCatalogExerciseGif(ExerciseMediaCache $exercise): bool
    {
        $workoutxName = trim((string) ($exercise->workoutx_name ?? ''));
        $gifUrl = trim((string) ($exercise->remote_gif_url ?? ''));

        if ($workoutxName === '' || $gifUrl === '') {
            return false;
        }

        $media = $this->storeGifFromUrl($workoutxName, $gifUrl);

        if ($media['path'] === '') {
            return false;
        }

        if ($exercise->storage_path !== $media['path']) {
            $exercise->storage_path = $media['path'];
            $exercise->save();
        }

        return true;
    }

    public function failWorkoutxSyncStatus(string $message): void
    {
        $status = $this->workoutxSyncStatus();

        Cache::forever($this->workoutxSyncStatusCacheKey(), [
            'state' => 'failed',
            'started_at' => $status['started_at'] ?: now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'requested_by_user_id' => $status['requested_by_user_id'],
            'message' => $message,
            'progress' => $status['progress'],
        ]);
    }

    private function resolveLocalGif(string $workoutxName, bool $isEnabled): array
    {
        if ($workoutxName === '') {
            return ['path' => '', 'url' => ''];
        }

        $disk = Storage::disk('public');
        $path = 'exercises/' . $workoutxName . '.gif';

        if ($disk->exists($path)) {
            return [
                'path' => $path,
                'url' => Storage::url($path),
            ];
        }

        if (! $isEnabled) {
            return ['path' => '', 'url' => ''];
        }

        $gifUrl = $this->fetchGifUrl($workoutxName);

        if ($gifUrl === null) {
            return ['path' => '', 'url' => ''];
        }

        $binary = $this->downloadGif($gifUrl);

        if ($binary === null) {
            return ['path' => '', 'url' => ''];
        }

        $disk->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::url($path),
        ];
    }

    private function fetchGifUrl(string $workoutxName): ?string
    {
        $payload = $this->fetchExercisePayload($workoutxName);

        if (! is_array($payload)) {
            return null;
        }

        return $this->extractGifUrl($payload);
    }

    private function fetchExercisePayload(string $workoutxName): ?array
    {
        return $this->requestWorkoutxJson('/exercises/name/' . rawurlencode($workoutxName));
    }

    private function requestWorkoutxJson(string $path, array $query = []): ?array
    {
        if ($this->workoutxApiBaseUrl() === '') {
            return null;
        }

        try {
            $response = $this->workoutxRequest()->get($this->workoutxUrl($path), $query);
        } catch (ConnectionException $exception) {
            Log::warning('WorkoutX request timed out or failed to connect.', [
                'path' => $path,
                'query' => $query,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('WorkoutX request returned a non-success status.', [
                'path' => $path,
                'query' => $query,
                'status' => $response->status(),
            ]);

            return null;
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : null;
    }

    private function extractExerciseCollection(array $payload): array
    {
        $candidates = [
            data_get($payload, 'data'),
            $payload,
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            if (! array_is_list($candidate)) {
                continue;
            }

            return array_values(array_filter($candidate, static fn($item) => is_array($item)));
        }

        return [];
    }

    private function upsertCatalogExercise(array $exercise): ?string
    {
        $remoteExerciseId = trim((string) data_get($exercise, 'id', ''));
        $name = trim((string) data_get($exercise, 'name', ''));

        if ($remoteExerciseId === '' && $name === '') {
            return null;
        }

        $workoutxName = $this->normalizeWorkoutxName($name, $remoteExerciseId !== '' ? 'exercise-' . $remoteExerciseId : 'body-weight-exercise');
        $gifUrl = trim((string) data_get($exercise, 'gifUrl', ''));

        $cache = null;

        if ($remoteExerciseId !== '') {
            $cache = ExerciseMediaCache::query()
                ->where('remote_exercise_id', $remoteExerciseId)
                ->first();
        }

        if (! $cache instanceof ExerciseMediaCache) {
            $cache = ExerciseMediaCache::query()
                ->where('workoutx_name', $workoutxName)
                ->first();
        }

        $attributes = [
            'remote_exercise_id' => $remoteExerciseId !== '' ? $remoteExerciseId : null,
            'workoutx_name' => $workoutxName,
            'query_name' => $name !== '' ? $name : null,
            'remote_gif_url' => $gifUrl !== '' ? $gifUrl : null,
            'storage_path' => $cache?->storage_path,
            'payload' => $exercise,
        ];

        if ($cache instanceof ExerciseMediaCache) {
            $cache->fill($attributes);

            if (! $cache->isDirty()) {
                return 'unchanged';
            }

            $cache->save();

            return 'updated';
        }

        ExerciseMediaCache::query()->create($attributes);

        return 'created';
    }

    private function extractGifUrl(array $payload): ?string
    {
        $candidates = [
            data_get($payload, 'gifUrl'),
            data_get($payload, 'data.gifUrl'),
            data_get($payload, 'exercise.gifUrl'),
            data_get($payload, 'data.exercise.gifUrl'),
            data_get($payload, '0.gifUrl'),
            data_get($payload, 'data.0.gifUrl'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = trim((string) $candidate);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $gifUrl = $this->extractGifUrl($value);

                if ($gifUrl !== null) {
                    return $gifUrl;
                }
            }
        }

        return null;
    }

    private function workoutxSyncStatusCacheKey(): string
    {
        return 'workoutx:catalog_sync_status';
    }

    private function workoutxGifSyncStatusCacheKey(): string
    {
        return 'workoutx:catalog_gif_sync_status';
    }

    private function pendingCatalogGifsQuery(): Builder
    {
        return ExerciseMediaCache::query()
            ->whereNotNull('remote_exercise_id')
            ->whereNotNull('remote_gif_url')
            ->where('remote_gif_url', '!=', '')
            ->where(function (Builder $query): void {
                $query->whereNull('storage_path')
                    ->orWhere('storage_path', '');
            });
    }

    private function extractExerciseData(array $payload): ?array
    {
        $candidates = [
            data_get($payload, 'data.0'),
            $this->extractExerciseCollection($payload)[0] ?? null,
            data_get($payload, 'exercise'),
            data_get($payload, 'data.exercise'),
            data_get($payload, '0'),
            $payload,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && trim((string) data_get($candidate, 'name', '')) !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function downloadGif(string $gifUrl): ?string
    {
        $requestTimeout = (int) config('services.workoutx.request_timeout', 20);

        try {
            $response = Http::connectTimeout($requestTimeout)
                ->timeout($requestTimeout)
                ->get($gifUrl);
        } catch (ConnectionException $exception) {
            Log::warning('WorkoutX GIF download timed out or failed to connect.', [
                'gif_url' => $gifUrl,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('WorkoutX GIF download returned a non-success status.', [
                'gif_url' => $gifUrl,
                'status' => $response->status(),
            ]);

            return null;
        }

        $binary = $response->body();

        return $binary !== '' ? $binary : null;
    }

    private function storeGifFromUrl(string $workoutxName, string $gifUrl): array
    {
        $binary = $this->downloadGif($gifUrl);

        if ($binary === null) {
            return ['path' => '', 'url' => ''];
        }

        $path = 'exercises/' . $workoutxName . '.gif';
        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::url($path),
        ];
    }

    private function buildLookupResponse(ExerciseMediaCache $cache, bool $cached, ?array $resolvedMedia = null): array
    {
        $media = $resolvedMedia ?? $this->resolveCachedMedia($cache);

        if ($media['path'] !== '' && $cache->storage_path !== $media['path']) {
            $cache->storage_path = $media['path'];
            $cache->save();
        }

        $exercise = $cache->payload;

        return [
            'found' => is_array($exercise) && $exercise !== [],
            'cached' => $cached,
            'query' => (string) ($cache->query_name ?: $cache->workoutx_name),
            'remote_exercise_id' => (string) ($cache->remote_exercise_id ?? ''),
            'localized_name_pt_br' => (string) ($cache->localized_name_pt_br ?? ''),
            'workoutx_name' => (string) $cache->workoutx_name,
            'exercise' => $exercise,
            'media' => $media,
        ];
    }

    private function resolveExerciseDisplayName(array $exercise, ?ExerciseMediaCache $catalogExercise): string
    {
        $incomingName = trim((string) data_get($exercise, 'name', ''));

        if (! $catalogExercise instanceof ExerciseMediaCache) {
            return $incomingName !== '' ? $incomingName : 'Exercicio';
        }

        $localizedName = trim((string) ($catalogExercise->localized_name_pt_br ?? ''));

        if ($localizedName !== '') {
            return $localizedName;
        }

        if ($this->shouldPersistLocalizedExerciseName($incomingName, $catalogExercise)) {
            $catalogExercise->localized_name_pt_br = $incomingName;
            $catalogExercise->save();

            return $incomingName;
        }

        return $incomingName !== '' ? $incomingName : $this->fallbackExerciseDisplayName($catalogExercise);
    }

    private function shouldPersistLocalizedExerciseName(string $incomingName, ExerciseMediaCache $catalogExercise): bool
    {
        if ($incomingName === '') {
            return false;
        }

        $officialNames = array_filter([
            trim((string) ($catalogExercise->query_name ?? '')),
            trim((string) ($catalogExercise->workoutx_name ?? '')),
            trim((string) data_get($catalogExercise->payload, 'name', '')),
        ]);

        foreach ($officialNames as $officialName) {
            if ($this->normalizeComparableName($incomingName) === $this->normalizeComparableName($officialName)) {
                return false;
            }
        }

        if (preg_match('/[áàâãéêíóôõúç]/iu', $incomingName) === 1) {
            return true;
        }

        $normalized = mb_strtolower($incomingName);
        $ptBrHints = [
            'agachamento',
            'supino',
            'remada',
            'puxada',
            'caminhada',
            'corrida',
            'prancha',
            'elevacao',
            'elevacao',
            'desenvolvimento',
            'flexao',
            'abdominal',
            'rosca',
            'crucifixo',
            'triceps',
            'biceps',
            'panturrilha',
            'levantamento',
            'afundo',
            'passada',
            'esteira',
            'bicicleta',
            'ombro',
            'costas',
            'peito',
            'pernas',
        ];

        foreach ($ptBrHints as $hint) {
            if (str_contains($normalized, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function fallbackExerciseDisplayName(ExerciseMediaCache $catalogExercise): string
    {
        $payloadName = trim((string) data_get($catalogExercise->payload, 'name', ''));

        if ($payloadName !== '') {
            return $payloadName;
        }

        return trim((string) ($catalogExercise->query_name ?: $catalogExercise->workoutx_name ?: 'Exercicio'));
    }

    private function normalizeComparableName(string $value): string
    {
        return $this->normalizeWorkoutxName($value, $value);
    }

    private function resolveCatalogExerciseForPlanExercise(array $exercise): ?ExerciseMediaCache
    {
        $remoteExerciseId = trim((string) data_get($exercise, 'remote_exercise_id', ''));

        if ($remoteExerciseId !== '') {
            $cache = ExerciseMediaCache::query()
                ->where('remote_exercise_id', $remoteExerciseId)
                ->first();

            if ($cache instanceof ExerciseMediaCache) {
                return $cache;
            }
        }

        $workoutxName = $this->normalizeWorkoutxName(
            data_get($exercise, 'workoutx_name', data_get($exercise, 'workoutx_lookup.name', '')),
            trim((string) data_get($exercise, 'name', 'Exercicio')),
        );

        return ExerciseMediaCache::query()
            ->where('workoutx_name', $workoutxName)
            ->first();
    }

    private function resolveCachedMedia(ExerciseMediaCache $cache): array
    {
        $path = trim((string) ($cache->storage_path ?? ''));

        if ($path !== '' && Storage::disk('public')->exists($path)) {
            return [
                'path' => $path,
                'url' => Storage::url($path),
            ];
        }

        $gifUrl = trim((string) ($cache->remote_gif_url ?? ''));

        if ($gifUrl === '' || ! $this->isEnabled()) {
            return [
                'path' => '',
                'url' => '',
            ];
        }

        return $this->storeGifFromUrl((string) $cache->workoutx_name, $gifUrl);
    }

    private function workoutxRequest(): PendingRequest
    {
        $requestTimeout = (int) config('services.workoutx.request_timeout', 20);
        $apiKey = $this->workoutxApiKey();
        $authMode = (string) config('services.workoutx.auth_mode', 'header');

        $request = Http::connectTimeout($requestTimeout)
            ->timeout($requestTimeout)
            ->acceptJson();

        if ($apiKey === '') {
            return $request;
        }

        if ($authMode === 'query') {
            return $request->withQueryParameters([
                'api-key' => $apiKey,
            ]);
        }

        return $request->withHeaders([
            'X-WorkoutX-Key' => $apiKey,
        ]);
    }

    private function workoutxApiBaseUrl(): string
    {
        return rtrim((string) config('services.workoutx.api_base_url', ''), '/');
    }

    private function workoutxApiKey(): string
    {
        return trim((string) config('services.workoutx.api_key', ''));
    }

    private function workoutxUrl(string $path): string
    {
        return $this->workoutxApiBaseUrl() . '/' . ltrim($path, '/');
    }

    private function resolveCatalogRequestLimit(): int
    {
        $configured = (int) config('services.workoutx.default_limit', 10);

        return max(1, min($configured, 100));
    }

    private function isEnabled(): bool
    {
        return (bool) config('services.workoutx.enabled', false);
    }

    private function assetBuilder(): ExerciseAssetBuilder
    {
        return $this->assetBuilder ?? new ExerciseAssetBuilder();
    }
}
