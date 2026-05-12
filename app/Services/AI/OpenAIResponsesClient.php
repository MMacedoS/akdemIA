<?php

namespace App\Services\AI;

use App\Exceptions\AI\OpenAIRequestException;
use App\Exceptions\AI\OpenAITransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenAIResponsesClient
{
    public function createResponse(array $payload): array
    {
        return $this->sendJson('POST', '/responses', $payload);
    }

    public function uploadFile(string $filename, string $content, string $purpose = 'assistants'): array
    {
        return $this->sendMultipart('/files', $filename, $content, [
            'purpose' => $purpose,
        ]);
    }

    public function createVectorStore(array $payload): array
    {
        return $this->sendJson('POST', '/vector_stores', $payload);
    }

    public function attachFileToVectorStore(string $vectorStoreId, string $fileId): array
    {
        return $this->sendJson('POST', '/vector_stores/' . $vectorStoreId . '/files', [
            'file_id' => $fileId,
        ]);
    }

    public function searchVectorStore(string $vectorStoreId, string $query, int $maxResults = 24): array
    {
        return $this->sendJson('POST', '/vector_stores/' . $vectorStoreId . '/search', [
            'query' => $query,
            'max_num_results' => $maxResults,
        ]);
    }

    public function extractOutputText(array $body): string
    {
        $outputText = trim((string) ($body['output_text'] ?? ''));

        if ($outputText !== '') {
            return $outputText;
        }

        $chunks = [];

        foreach (($body['output'] ?? []) as $output) {
            foreach (($output['content'] ?? []) as $content) {
                $text = trim((string) ($content['text'] ?? data_get($content, 'output_text.text', '')));

                if ($text !== '') {
                    $chunks[] = $text;
                }
            }
        }

        return trim(implode("\n", $chunks));
    }

    public function decodeStructuredOutput(array $body): array
    {
        $decoded = json_decode($this->stripCodeFence($this->extractOutputText($body)), true);

        if (! is_array($decoded)) {
            throw new OpenAIRequestException('Invalid structured JSON returned by Responses API.', 200, $body);
        }

        return $decoded;
    }

    private function sendJson(string $method, string $uri, array $payload): array
    {
        $lastException = null;
        $maxAttempts = $this->retryTimes();

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $startedAt = microtime(true);

            try {
                $response = $this->baseRequest()->send($method, $uri, [
                    'json' => $payload,
                ]);
            } catch (ConnectionException $exception) {
                $lastException = $exception;

                if ($attempt < $maxAttempts) {
                    usleep($this->retrySleepMicroseconds($attempt));
                    continue;
                }

                throw new OpenAITransportException('Falha de conexão ao consultar a OpenAI.', previous: $exception);
            }

            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($this->shouldRetryResponse($response) && $attempt < $maxAttempts) {
                usleep($this->retrySleepMicroseconds($attempt));
                continue;
            }

            if (! $response->successful()) {
                throw new OpenAIRequestException(
                    'OpenAI request failed with status ' . $response->status() . '.',
                    $response->status(),
                    $response->json() ?? [],
                );
            }

            return [
                'body' => $response->json() ?? [],
                'status' => $response->status(),
                'headers' => $response->headers(),
                'latency_ms' => $latencyMs,
            ];
        }

        throw new OpenAITransportException('Falha inesperada ao consultar a OpenAI.', previous: $lastException);
    }

    private function sendMultipart(string $uri, string $filename, string $content, array $fields): array
    {
        $lastException = null;
        $maxAttempts = $this->retryTimes();

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $startedAt = microtime(true);

            try {
                $response = $this->baseRequest()
                    ->attach('file', $content, $filename)
                    ->post($uri, $fields);
            } catch (ConnectionException $exception) {
                $lastException = $exception;

                if ($attempt < $maxAttempts) {
                    usleep($this->retrySleepMicroseconds($attempt));
                    continue;
                }

                throw new OpenAITransportException('Falha de conexão ao enviar arquivo para a OpenAI.', previous: $exception);
            }

            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($this->shouldRetryResponse($response) && $attempt < $maxAttempts) {
                usleep($this->retrySleepMicroseconds($attempt));
                continue;
            }

            if (! $response->successful()) {
                throw new OpenAIRequestException(
                    'OpenAI file upload failed with status ' . $response->status() . '.',
                    $response->status(),
                    $response->json() ?? [],
                );
            }

            return [
                'body' => $response->json() ?? [],
                'status' => $response->status(),
                'headers' => $response->headers(),
                'latency_ms' => $latencyMs,
            ];
        }

        throw new OpenAITransportException('Falha inesperada ao enviar arquivo para a OpenAI.', previous: $lastException);
    }

    private function baseRequest(): PendingRequest
    {
        $apiKey = trim((string) config('services.openai.api_key'));

        if ($apiKey === '') {
            throw new OpenAITransportException('OpenAI API key is missing.');
        }

        return Http::baseUrl('https://api.openai.com/v1')
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->connectTimeout((int) config('services.openai.connect_timeout', 20))
            ->timeout((int) config('services.openai.timeout', 90));
    }

    private function retryTimes(): int
    {
        return max(1, (int) config('services.openai.retry_times', 3));
    }

    private function retrySleepMicroseconds(int $attempt): int
    {
        return max(1, (int) config('services.openai.retry_sleep_ms', 1200) * $attempt) * 1000;
    }

    private function shouldRetryResponse(Response $response): bool
    {
        return in_array($response->status(), [408, 409, 429, 500, 502, 503, 504], true);
    }

    private function stripCodeFence(string $content): string
    {
        $trimmed = trim($content);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```json\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/^```\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }
}