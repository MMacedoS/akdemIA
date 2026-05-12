<?php

namespace App\DTOs\AI;

final readonly class VectorStoreSyncResult
{
    public function __construct(
        public string $vectorStoreId,
        public ?string $fileId,
        public string $storagePath,
        public string $sourceHash,
        public bool $reused,
        public ?int $tenantId,
        public string $catalogType,
        public array $metadata = [],
    ) {}
}
