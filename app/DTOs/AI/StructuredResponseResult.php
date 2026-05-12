<?php

namespace App\DTOs\AI;

final readonly class StructuredResponseResult
{
    public function __construct(
        public array $data,
        public array $rawResponse,
        public bool $cacheHit,
        public string $cacheKey,
        public ?string $model,
        public ?int $latencyMs,
        public array $usage,
        public ?string $responseId,
    ) {}
}