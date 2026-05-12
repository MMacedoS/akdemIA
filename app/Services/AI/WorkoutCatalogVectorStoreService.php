<?php

namespace App\Services\AI;

use App\DTOs\AI\VectorStoreSyncResult;
use App\Models\AI\AiVectorStore;
use App\Services\Workouts\ExerciseCatalogService;
use Illuminate\Support\Facades\Storage;

class WorkoutCatalogVectorStoreService
{
    public function __construct(
        private readonly ExerciseCatalogService $exerciseCatalogService,
        private readonly OpenAIResponsesClient $client,
        private readonly AiRequestLogger $logger,
    ) {}

    public function ensureSynced(?int $tenantId): VectorStoreSyncResult
    {
        $scopedTenantId = $this->scopedTenantId($tenantId);
        $export = $this->exerciseCatalogService->exportVectorStoreDocument();
        $storagePath = (string) $export['path'];
        $disk = (string) ($export['disk'] ?? 'local');
        $fileContents = (string) Storage::disk($disk)->get($storagePath);
        $sourceHash = hash('sha256', $fileContents);
        $catalogType = (string) config('services.openai.vector_store.catalog_type', 'workout_exercises');

        $record = AiVectorStore::query()
            ->where('tenant_id', $scopedTenantId)
            ->where('catalog_type', $catalogType)
            ->latest('id')
            ->first();

        if ($record instanceof AiVectorStore
            && $record->source_hash === $sourceHash
            && $record->vector_store_id !== ''
            && $record->file_id !== null
        ) {
            $record->forceFill([
                'last_used_at' => now(),
                'status' => 'ready',
            ])->save();

            return new VectorStoreSyncResult(
                vectorStoreId: $record->vector_store_id,
                fileId: $record->file_id,
                storagePath: $record->storage_path,
                sourceHash: $record->source_hash,
                reused: true,
                tenantId: $scopedTenantId,
                catalogType: $catalogType,
                metadata: $record->metadata ?? [],
            );
        }

        $vectorStoreId = $record?->vector_store_id;
        $vectorStoreName = $this->vectorStoreName($scopedTenantId);

        if ($vectorStoreId === null || $vectorStoreId === '') {
            $vectorStore = $this->client->createVectorStore([
                'name' => $vectorStoreName,
                'metadata' => [
                    'catalog_type' => $catalogType,
                    'tenant_id' => $scopedTenantId,
                    'scope' => $this->scope(),
                ],
            ]);

            $vectorStoreId = (string) data_get($vectorStore, 'body.id', '');
        }

        $uploadedFile = $this->client->uploadFile(
            basename($storagePath),
            $fileContents,
            (string) config('services.openai.vector_store.file_purpose', 'assistants'),
        );
        $fileId = (string) data_get($uploadedFile, 'body.id', '');
        $attachment = $this->client->attachFileToVectorStore($vectorStoreId, $fileId);

        $record ??= new AiVectorStore();
        $record->forceFill([
            'tenant_id' => $scopedTenantId,
            'catalog_type' => $catalogType,
            'vector_store_id' => $vectorStoreId,
            'vector_store_name' => $vectorStoreName,
            'file_id' => $fileId,
            'storage_disk' => $disk,
            'storage_path' => $storagePath,
            'source_hash' => $sourceHash,
            'status' => (string) data_get($attachment, 'body.status', 'ready'),
            'last_synced_at' => now(),
            'last_used_at' => now(),
            'metadata' => [
                'export' => $export,
                'attachment' => $attachment['body'] ?? [],
                'scope' => $this->scope(),
                'requested_tenant_id' => $tenantId,
            ],
        ])->save();

        $this->logger->log([
            'tenant_id' => $tenantId,
            'type' => 'workout',
            'operation' => 'vector_store_sync',
            'http_status' => (int) ($attachment['status'] ?? $uploadedFile['status'] ?? 200),
            'latency_ms' => (int) (($uploadedFile['latency_ms'] ?? 0) + ($attachment['latency_ms'] ?? 0)),
            'vector_store_id' => $vectorStoreId,
            'file_id' => $fileId,
            'request_payload' => [
                'storage_path' => $storagePath,
                'catalog_type' => $catalogType,
            ],
            'response_payload' => [
                'vector_store_id' => $vectorStoreId,
                'file_id' => $fileId,
                'status' => data_get($attachment, 'body.status'),
            ],
            'metadata' => [
                'reused' => false,
                'tenant_id' => $scopedTenantId,
                'scope' => $this->scope(),
                'requested_tenant_id' => $tenantId,
            ],
        ]);

        return new VectorStoreSyncResult(
            vectorStoreId: $vectorStoreId,
            fileId: $fileId,
            storagePath: $storagePath,
            sourceHash: $sourceHash,
            reused: false,
            tenantId: $scopedTenantId,
            catalogType: $catalogType,
            metadata: $record->metadata ?? [],
        );
    }

    private function vectorStoreName(?int $tenantId): string
    {
        $prefix = trim((string) config('services.openai.vector_store.name_prefix', 'akdemia-workouts'));

        return $prefix . '-' . ($tenantId ?? 'global');
    }

    private function scopedTenantId(?int $tenantId): ?int
    {
        return $this->scope() === 'tenant' ? $tenantId : null;
    }

    private function scope(): string
    {
        return (string) config('services.openai.vector_store.scope', 'global');
    }
}