<?php

namespace Tests\Unit\Services\Workouts;

use App\Models\Workout\ExerciseMediaCache;
use App\Services\Workouts\ExerciseCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ExerciseCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_ai_catalog_snapshot_groups_by_focus_and_uses_bucket_limit(): void
    {
        config()->set('services.internal_catalog.ai_bucket_limit', 1);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0001',
            'localized_name_pt_br' => 'Supino reto',
            'workoutx_name' => 'barbell-bench-press',
            'query_name' => 'Barbell Bench Press',
            'payload' => ['id' => '0001', 'name' => 'Barbell Bench Press', 'bodyPart' => 'chest', 'target' => 'pectorals', 'equipment' => 'barbell'],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0002',
            'localized_name_pt_br' => 'Crucifixo',
            'workoutx_name' => 'cable-fly',
            'query_name' => 'Cable Fly',
            'payload' => ['id' => '0002', 'name' => 'Cable Fly', 'bodyPart' => 'chest', 'target' => 'pectorals', 'equipment' => 'cable'],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '1001',
            'localized_name_pt_br' => 'Caminhada inclinada',
            'workoutx_name' => 'incline-treadmill-walk',
            'query_name' => 'Incline Treadmill Walk',
            'payload' => ['id' => '1001', 'name' => 'Incline Treadmill Walk', 'bodyPart' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'treadmill'],
        ]);

        $snapshot = app(ExerciseCatalogService::class)->buildAiCatalogSnapshot();

        $this->assertSame(1, $snapshot['bucket_limit']);
        $this->assertArrayHasKey('peito', $snapshot['catalog']);
        $this->assertArrayHasKey('cardio', $snapshot['catalog']);
        $this->assertCount(1, $snapshot['catalog']['peito']);
        $this->assertSame('Crucifixo', $snapshot['catalog']['peito'][0]['localized_name_pt_br']);
    }

    public function test_export_ai_catalog_document_writes_storage_file_and_prompt_snapshot_uses_it(): void
    {
        Storage::fake('local');
        config()->set('services.internal_catalog.storage_path', 'ai/test-openai-workout-catalog.json');
        config()->set('services.internal_catalog.ai_prompt_bucket_limit', 2);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0001',
            'localized_name_pt_br' => 'Supino reto',
            'workoutx_name' => 'barbell-bench-press',
            'query_name' => 'Barbell Bench Press',
            'payload' => ['id' => '0001', 'name' => 'Barbell Bench Press', 'bodyPart' => 'chest', 'target' => 'pectorals', 'equipment' => 'barbell'],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0002',
            'localized_name_pt_br' => 'Crucifixo',
            'workoutx_name' => 'cable-fly',
            'query_name' => 'Cable Fly',
            'payload' => ['id' => '0002', 'name' => 'Cable Fly', 'bodyPart' => 'chest', 'target' => 'pectorals', 'equipment' => 'cable'],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '1001',
            'localized_name_pt_br' => 'Caminhada inclinada',
            'workoutx_name' => 'incline-treadmill-walk',
            'query_name' => 'Incline Treadmill Walk',
            'payload' => ['id' => '1001', 'name' => 'Incline Treadmill Walk', 'bodyPart' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'treadmill'],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0003',
            'localized_name_pt_br' => 'Supino inclinado',
            'workoutx_name' => 'incline-bench-press',
            'query_name' => 'Incline Bench Press',
            'payload' => ['id' => '0003', 'name' => 'Incline Bench Press', 'bodyPart' => 'chest', 'target' => 'pectorals', 'equipment' => 'barbell'],
        ]);

        $result = app(ExerciseCatalogService::class)->exportAiCatalogDocument();

        $this->assertTrue(Storage::disk('local')->exists('ai/test-openai-workout-catalog.json'));
        $this->assertSame('ai/test-openai-workout-catalog.json', $result['path']);
        $this->assertSame(4, data_get($result, 'meta.total'));

        $snapshot = app(ExerciseCatalogService::class)->buildAiPromptCatalogSnapshot();

        $this->assertSame(2, $snapshot['bucket_limit']);
        $this->assertSame(4, $snapshot['catalog_total']);
        $this->assertSame('ai/test-openai-workout-catalog.json', $snapshot['storage_path']);
        $this->assertCount(3, $snapshot['catalog']['peito']);
    }

    public function test_build_ai_prompt_catalog_snapshot_falls_back_to_stored_document_when_database_is_unavailable(): void
    {
        Storage::fake('local');
        config()->set('services.internal_catalog.storage_path', 'ai/test-openai-workout-catalog.json');
        config()->set('services.internal_catalog.ai_prompt_bucket_limit', 5);

        Storage::disk('local')->put('ai/test-openai-workout-catalog.json', json_encode([
            'meta' => [
                'total' => 1,
                'max_updated_at' => '2026-05-10T12:24:52+00:00',
                'generated_at' => '2026-05-12T19:02:06+00:00',
                'storage_path' => 'ai/test-openai-workout-catalog.json',
                'focuses' => ['peito'],
            ],
            'catalog' => [
                'peito' => [[
                    'id' => '0009',
                    'localized_name_pt_br' => 'Supino reto com barra',
                    'name' => 'Barbell Bench Press',
                    'workoutx_name' => 'barbell-bench-press',
                    'target' => 'pectorals',
                    'equipment' => 'barbell',
                    'body_part' => 'chest',
                    'focus' => 'peito',
                    'translation_status' => 'translated',
                ]],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $service = Mockery::mock(ExerciseCatalogService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('catalogDocumentMeta')
            ->once()
            ->andThrow(new \RuntimeException('database unavailable'));

        $snapshot = $service->buildAiPromptCatalogSnapshot();

        $this->assertSame(5, $snapshot['bucket_limit']);
        $this->assertSame(1, $snapshot['catalog_total']);
        $this->assertSame('ai/test-openai-workout-catalog.json', $snapshot['storage_path']);
        $this->assertSame('barbell-bench-press', $snapshot['catalog']['peito'][0]['workoutx_name']);
    }
}
