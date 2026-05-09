<?php

namespace Tests\Feature\Api\V1\Workouts;

use App\Models\Workout\ExerciseMediaCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalExerciseCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_catalog_endpoint_requires_valid_api_key(): void
    {
        config()->set('services.internal_catalog.api_key', 'catalog-secret');

        $response = $this->getJson('/api/v1/internal/catalog/exercises');

        $response->assertUnauthorized();
    }

    public function test_internal_catalog_endpoint_returns_filtered_catalog(): void
    {
        config()->set('services.internal_catalog.api_key', 'catalog-secret');

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0001',
            'localized_name_pt_br' => 'Supino reto',
            'workoutx_name' => 'barbell-bench-press',
            'query_name' => 'Barbell Bench Press',
            'payload' => ['id' => '0001', 'name' => 'Barbell Bench Press', 'bodyPart' => 'chest', 'target' => 'pectorals', 'equipment' => 'barbell'],
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '1001',
            'localized_name_pt_br' => 'Caminhada inclinada',
            'workoutx_name' => 'incline-treadmill-walk',
            'query_name' => 'Incline Treadmill Walk',
            'payload' => ['id' => '1001', 'name' => 'Incline Treadmill Walk', 'bodyPart' => 'cardio', 'target' => 'cardiovascular system', 'equipment' => 'treadmill'],
        ]);

        $response = $this->withHeaders([
            'X-Internal-Catalog-Key' => 'catalog-secret',
        ])->getJson('/api/v1/internal/catalog/exercises?focus=peito&search=supino&translation_status=translated');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.id', '0001');
        $response->assertJsonPath('data.0.localized_name_pt_br', 'Supino reto');
        $response->assertJsonPath('data.0.focus', 'peito');
        $response->assertJsonPath('data.0.translation_status', 'translated');
    }
}
