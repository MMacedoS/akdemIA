<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;

class AiResponseCacheService
{
    public function buildKey(string $namespace, array $parts): string
    {
        return 'ai:' . $namespace . ':' . hash('sha256', json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function get(string $key): ?array
    {
        $cached = Cache::get($key);

        return is_array($cached) ? $cached : null;
    }

    public function put(string $key, array $payload, ?int $ttlSeconds = null): void
    {
        Cache::put($key, $payload, $ttlSeconds ?? (int) config('services.openai.workout_cache_ttl', 3600));
    }
}