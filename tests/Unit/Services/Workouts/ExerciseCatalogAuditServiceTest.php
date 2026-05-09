<?php

namespace Tests\Unit\Services\Workouts;

use App\Models\Workout\ExerciseMediaCache;
use App\Services\Workouts\ExerciseCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseCatalogAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_catalog_filters_by_translation_status_and_focus(): void
    {
        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0001',
            'localized_name_pt_br' => 'Supino reto',
            'workoutx_name' => 'barbell-bench-press',
            'query_name' => 'Barbell Bench Press',
            'payload' => ['id' => '0001', 'name' => 'Barbell Bench Press', 'bodyPart' => 'chest', 'target' => 'pectorals', 'equipment' => 'barbell'],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0002',
            'localized_name_pt_br' => null,
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

        $audit = app(ExerciseCatalogService::class)->auditCatalog('peito', '', 'pending', 25, 1);

        $this->assertSame(3, $audit['summary']['total']);
        $this->assertSame(2, $audit['summary']['translated']);
        $this->assertSame(1, $audit['summary']['pending_translation']);
        $this->assertCount(1, $audit['rows']);
        $this->assertSame('0002', $audit['rows'][0]['id']);
        $this->assertSame('pending', $audit['rows'][0]['translation_status']);
        $this->assertContains('peito', $audit['available_focuses']);
    }
}
