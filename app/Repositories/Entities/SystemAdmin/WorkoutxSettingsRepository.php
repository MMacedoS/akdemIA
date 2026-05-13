<?php

namespace App\Repositories\Entities\SystemAdmin;

use App\Models\SystemSetting;
use App\Repositories\Contracts\SystemAdmin\WorkoutxSettingsRepositoryContract;
use Illuminate\Support\Collection;

class WorkoutxSettingsRepository implements WorkoutxSettingsRepositoryContract
{
    public function values(): Collection
    {
        return SystemSetting::query()
            ->whereIn('key', [
                'workoutx.enabled',
                'workoutx.api_base_url',
                'workoutx.api_key',
                'workoutx.auth_mode',
                'workoutx.request_timeout',
                'workoutx.default_limit',
                'workoutx.sync_page_delay_seconds',
                'workoutx.allow_fallback',
                'openai.vector_store.enabled',
                'openai.vector_store.scope',
                'openai.vector_store.catalog_type',
                'openai.vector_store.name_prefix',
                'openai.vector_store.existing_id',
                'openai.vector_store.existing_name',
                'openai.vector_store.file_purpose',
                'openai.vector_store.max_search_results',
                'openai.vector_store.minimum_candidates',
                'internal_catalog.vector_store_storage_path',
            ])
            ->pluck('value', 'key');
    }

    public function update(array $payload): void
    {
        $this->upsert('workoutx.enabled', $payload['workoutx_enabled'] ?? '0', false);
        $this->upsert('workoutx.api_base_url', $payload['workoutx_api_base_url'] ?? null, false);
        $this->upsert('workoutx.api_key', $payload['workoutx_api_key'] ?? null, true);
        $this->upsert('workoutx.auth_mode', $payload['workoutx_auth_mode'] ?? 'header', false);
        $this->upsert('workoutx.request_timeout', isset($payload['workoutx_request_timeout']) ? (string) $payload['workoutx_request_timeout'] : null, false);
        $this->upsert('workoutx.default_limit', isset($payload['workoutx_default_limit']) ? (string) $payload['workoutx_default_limit'] : null, false);
        $this->upsert('workoutx.sync_page_delay_seconds', isset($payload['workoutx_sync_page_delay_seconds']) ? (string) $payload['workoutx_sync_page_delay_seconds'] : null, false);
        $this->upsert('workoutx.allow_fallback', $payload['workoutx_allow_fallback'] ?? '0', false);
        $this->upsert('openai.vector_store.enabled', $payload['vector_store_enabled'] ?? '0', false);
        $this->upsert('openai.vector_store.scope', $payload['vector_store_scope'] ?? 'global', false);
        $this->upsert('openai.vector_store.catalog_type', $payload['vector_store_catalog_type'] ?? 'workout_exercises', false);
        $this->upsert('openai.vector_store.name_prefix', $payload['vector_store_name_prefix'] ?? 'akdemia-workouts', false);
        $this->upsert('openai.vector_store.existing_id', $payload['vector_store_existing_id'] ?? null, false);
        $this->upsert('openai.vector_store.existing_name', $payload['vector_store_existing_name'] ?? null, false);
        $this->upsert('openai.vector_store.file_purpose', $payload['vector_store_file_purpose'] ?? 'assistants', false);
        $this->upsert('openai.vector_store.max_search_results', isset($payload['vector_store_max_search_results']) ? (string) $payload['vector_store_max_search_results'] : null, false);
        $this->upsert('openai.vector_store.minimum_candidates', isset($payload['vector_store_minimum_candidates']) ? (string) $payload['vector_store_minimum_candidates'] : null, false);
        $this->upsert('internal_catalog.vector_store_storage_path', $payload['vector_store_storage_path'] ?? 'ai/openai-workout-catalog.jsonl', false);
    }

    private function upsert(string $key, ?string $value, bool $isSecret): void
    {
        $normalized = is_string($value) ? trim($value) : null;

        if ($isSecret && ($normalized === null || $normalized === '')) {
            return;
        }

        if ($normalized === '') {
            $normalized = null;
        }

        SystemSetting::query()->updateOrCreate(
            [
                'domain' => 'workoutx',
                'key' => $key,
            ],
            [
                'value' => $normalized,
                'is_secret' => $isSecret,
            ]
        );
    }
}
