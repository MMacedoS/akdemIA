<?php

namespace App\Services\Workouts;

use App\Models\Workout\ExerciseMediaCache;
use App\Support\Workout\ExerciseAssetBuilder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

                        $workoutxName = $this->normalizeWorkoutxName(
                            data_get($exercise, 'workoutx_name', data_get($exercise, 'workoutx_lookup.name', '')),
                            $name,
                        );

                        data_set($exercise, 'workoutx_name', $workoutxName);

                        $media = $this->resolveLocalGif($workoutxName, $isEnabled);

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
                'query_name' => $queryName,
                'remote_gif_url' => $gifUrl !== '' ? $gifUrl : null,
                'storage_path' => $media['path'] !== '' ? $media['path'] : null,
                'payload' => $exercise,
            ]
        );

        return $this->buildLookupResponse($cache, false, $media);
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
        $apiBaseUrl = rtrim((string) config('services.workoutx.api_base_url', ''), '/');
        $apiKey = trim((string) config('services.workoutx.api_key', ''));

        if ($apiBaseUrl === '') {
            return null;
        }

        $requestTimeout = (int) config('services.workoutx.request_timeout', 20);
        $authMode = (string) config('services.workoutx.auth_mode', 'header');

        try {
            $request = Http::connectTimeout($requestTimeout)
                ->timeout($requestTimeout)
                ->acceptJson();

            if ($apiKey !== '') {
                if ($authMode === 'query') {
                    $request = $request->withQueryParameters([
                        'api-key' => $apiKey,
                    ]);
                } else {
                    $request = $request->withHeaders([
                        'X-WorkoutX-Key' => $apiKey,
                    ]);
                }
            }

            $response = $request->get($apiBaseUrl . '/exercises/name/' . rawurlencode($workoutxName));
        } catch (ConnectionException $exception) {
            Log::warning('WorkoutX request timed out or failed to connect.', [
                'workoutx_name' => $workoutxName,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('WorkoutX request returned a non-success status.', [
                'workoutx_name' => $workoutxName,
                'status' => $response->status(),
            ]);

            return null;
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return null;
        }

        return $payload;
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

    private function extractExerciseData(array $payload): ?array
    {
        $candidates = [
            data_get($payload, 'data.0'),
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
            'workoutx_name' => (string) $cache->workoutx_name,
            'exercise' => $exercise,
            'media' => $media,
        ];
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

    private function isEnabled(): bool
    {
        return (bool) config('services.workoutx.enabled', false);
    }

    private function assetBuilder(): ExerciseAssetBuilder
    {
        return $this->assetBuilder ?? new ExerciseAssetBuilder();
    }
}
