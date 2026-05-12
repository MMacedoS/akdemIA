<?php

namespace App\Services\AI;

use App\Models\AI\AiLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiRequestLogger
{
    public function log(array $payload): void
    {
        $requestPayload = is_array($payload['request_payload'] ?? null) ? $payload['request_payload'] : [];
        $responsePayload = is_array($payload['response_payload'] ?? null) ? $payload['response_payload'] : [];
        $responseSize = strlen(json_encode($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $promptHash = md5(json_encode(data_get($requestPayload, 'input', data_get($requestPayload, 'messages', [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $requestHash = hash('sha256', json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];

        AiLog::query()->create([
            'tenant_id' => $payload['tenant_id'] ?? null,
            'user_id' => $payload['user_id'] ?? null,
            'type' => $payload['type'] ?? 'unknown',
            'operation' => $payload['operation'] ?? 'unknown',
            'provider' => 'openai',
            'model' => $payload['model'] ?? null,
            'prompt_hash' => $promptHash,
            'request_hash' => $requestHash,
            'response_size' => $responseSize,
            'cache_key' => $payload['cache_key'] ?? null,
            'cache_hit' => (bool) ($payload['cache_hit'] ?? false),
            'retrieval_mode' => $payload['retrieval_mode'] ?? null,
            'vector_store_id' => $payload['vector_store_id'] ?? null,
            'file_id' => $payload['file_id'] ?? null,
            'http_status' => $payload['http_status'] ?? null,
            'latency_ms' => $payload['latency_ms'] ?? null,
            'prompt_tokens' => data_get($usage, 'input_tokens'),
            'completion_tokens' => data_get($usage, 'output_tokens'),
            'total_tokens' => data_get($usage, 'total_tokens'),
            'metadata' => $payload['metadata'] ?? [],
        ]);

        $this->writeDebugLog($payload + [
            'request_hash' => $requestHash,
            'prompt_hash' => $promptHash,
            'response_size' => $responseSize,
        ]);
    }

    private function writeDebugLog(array $payload): void
    {
        $path = $this->logPath();

        try {
            File::ensureDirectoryExists(dirname($path));

            File::append($path, json_encode([
                'created_at' => now()->toIso8601String(),
                'type' => $payload['type'] ?? 'unknown',
                'operation' => $payload['operation'] ?? 'unknown',
                'tenant_id' => $payload['tenant_id'] ?? null,
                'user_id' => $payload['user_id'] ?? null,
                'cache_key' => $payload['cache_key'] ?? null,
                'cache_hit' => (bool) ($payload['cache_hit'] ?? false),
                'retrieval_mode' => $payload['retrieval_mode'] ?? null,
                'vector_store_id' => $payload['vector_store_id'] ?? null,
                'file_id' => $payload['file_id'] ?? null,
                'http_status' => $payload['http_status'] ?? null,
                'latency_ms' => $payload['latency_ms'] ?? null,
                'request_hash' => $payload['request_hash'] ?? null,
                'prompt_hash' => $payload['prompt_hash'] ?? null,
                'response_size' => $payload['response_size'] ?? null,
                'request_payload' => $payload['request_payload'] ?? [],
                'response_payload' => $payload['response_payload'] ?? [],
                'metadata' => $payload['metadata'] ?? [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        } catch (Throwable $exception) {
            Log::warning('Falha ao gravar log detalhado da OpenAI.', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function logPath(): string
    {
        $configuredPath = trim((string) config('services.openai.prompt_log_path', 'logs/ai-prompts.log'));

        if ($configuredPath === '') {
            $configuredPath = 'logs/ai-prompts.log';
        }

        if (str_starts_with($configuredPath, '/')) {
            return $configuredPath;
        }

        return storage_path($configuredPath);
    }
}