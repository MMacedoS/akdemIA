<?php

namespace Tests\Unit\Services\Workouts;

use App\Models\Workout\ExerciseMediaCache;
use App\Services\Workouts\ExerciseCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
