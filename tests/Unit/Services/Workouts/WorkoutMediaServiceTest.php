<?php

namespace Tests\Unit\Services\Workouts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Workout\ExerciseMediaCache;
use App\Services\Workouts\WorkoutMediaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class WorkoutMediaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function configureIsolatedPublicDisk(): void
    {
        $root = storage_path('framework/testing/disks/public-workout-media-' . bin2hex(random_bytes(5)));

        config()->set('filesystems.disks.public.root', $root);
        config()->set('filesystems.disks.public.url', '/storage');

        app('files')->ensureDirectoryExists($root);
        app('filesystem')->forgetDisk('public');
    }

    public function test_enrich_workout_plan_downloads_and_stores_gif_from_workoutx(): void
    {
        $this->configureIsolatedPublicDisk();

        config()->set('services.workoutx.enabled', true);
        config()->set('services.workoutx.api_base_url', 'https://api.workoutxapp.com/v1');
        config()->set('services.workoutx.api_key', '');
        config()->set('services.workoutx.auth_mode', 'header');
        config()->set('services.workoutx.request_timeout', 5);
        config()->set('services.workoutx.allow_fallback', false);

        Http::fake([
            'https://api.workoutxapp.com/v1/exercises/name/bench-press' => Http::response([
                'total' => 32,
                'count' => 10,
                'data' => [
                    [
                        'id' => '0025',
                        'name' => 'Barbell Bench Press',
                        'gifUrl' => 'https://cdn.workoutx.test/bench-press.gif',
                    ],
                ],
            ], 200),
            'https://cdn.workoutx.test/bench-press.gif' => Http::response('gif-binary', 200, [
                'Content-Type' => 'image/gif',
            ]),
        ]);

        $service = new WorkoutMediaService();

        $result = $service->enrichWorkoutPlan([
            'weekly_plan' => [
                [
                    'day' => 'Segunda',
                    'focus' => 'Peito',
                    'exercises' => [
                        [
                            'name' => 'Supino reto',
                            'category' => 'specific',
                            'sets' => 4,
                            'reps' => '8-12',
                            'rest' => '60s',
                            'notes' => 'Controle o movimento.',
                            'steps' => ['A', 'B'],
                            'workoutx_name' => 'bench press',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('bench-press', data_get($result, 'weekly_plan.0.exercises.0.workoutx_name'));
        $this->assertSame('exercises/bench-press.gif', data_get($result, 'weekly_plan.0.exercises.0.exercise_media_path'));
        $this->assertSame('/storage/exercises/bench-press.gif', data_get($result, 'weekly_plan.0.exercises.0.exercise_media_url'));
        $this->assertSame('', data_get($result, 'weekly_plan.0.exercises.0.illustration_svg'));
        $this->assertTrue(Storage::disk('public')->exists('exercises/bench-press.gif'));
        Http::assertSentCount(2);
    }

    public function test_enrich_workout_plan_reuses_local_cached_gif_without_new_api_call(): void
    {
        $this->configureIsolatedPublicDisk();
        Storage::disk('public')->put('exercises/bench-press.gif', 'gif-binary');

        config()->set('services.workoutx.enabled', true);
        config()->set('services.workoutx.allow_fallback', false);

        Http::fake();

        $service = new WorkoutMediaService();

        $result = $service->enrichWorkoutPlan([
            'weekly_plan' => [
                [
                    'day' => 'Segunda',
                    'focus' => 'Peito',
                    'exercises' => [
                        [
                            'name' => 'Supino reto',
                            'category' => 'specific',
                            'sets' => 4,
                            'reps' => '8-12',
                            'rest' => '60s',
                            'notes' => 'Controle o movimento.',
                            'steps' => ['A', 'B'],
                            'workoutx_name' => 'bench press',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('/storage/exercises/bench-press.gif', data_get($result, 'weekly_plan.0.exercises.0.exercise_media_url'));
        Http::assertNothingSent();
    }

    public function test_enrich_workout_plan_uses_local_catalog_remote_id_and_downloads_gif_locally(): void
    {
        $this->configureIsolatedPublicDisk();

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0025',
            'workoutx_name' => 'barbell-bench-press',
            'query_name' => 'Barbell Bench Press',
            'remote_gif_url' => 'https://cdn.workoutx.test/barbell-bench-press.gif',
            'payload' => [
                'id' => '0025',
                'name' => 'Barbell Bench Press',
                'gifUrl' => 'https://cdn.workoutx.test/barbell-bench-press.gif',
            ],
        ]);

        config()->set('services.workoutx.enabled', true);
        config()->set('services.workoutx.request_timeout', 5);

        Http::fake([
            'https://cdn.workoutx.test/barbell-bench-press.gif' => Http::response('gif-binary', 200, [
                'Content-Type' => 'image/gif',
            ]),
        ]);

        $service = new WorkoutMediaService();

        $result = $service->enrichWorkoutPlan([
            'weekly_plan' => [
                [
                    'day' => 'Segunda',
                    'focus' => 'Peito',
                    'exercises' => [
                        [
                            'name' => 'Supino reto',
                            'category' => 'specific',
                            'sets' => 4,
                            'reps' => '8-12',
                            'rest' => '60s',
                            'notes' => 'Controle o movimento.',
                            'steps' => ['A', 'B'],
                            'remote_exercise_id' => '0025',
                            'workoutx_name' => 'qualquer-coisa-antiga',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('0025', data_get($result, 'weekly_plan.0.exercises.0.remote_exercise_id'));
        $this->assertSame('barbell-bench-press', data_get($result, 'weekly_plan.0.exercises.0.workoutx_name'));
        $this->assertSame('exercises/barbell-bench-press.gif', data_get($result, 'weekly_plan.0.exercises.0.exercise_media_path'));
        $this->assertSame('/storage/exercises/barbell-bench-press.gif', data_get($result, 'weekly_plan.0.exercises.0.exercise_media_url'));
        $this->assertTrue(Storage::disk('public')->exists('exercises/barbell-bench-press.gif'));
        Http::assertSentCount(1);
    }

    public function test_enrich_workout_plan_persists_and_reuses_localized_name_in_pt_br(): void
    {
        $this->configureIsolatedPublicDisk();
        Storage::disk('public')->put('exercises/barbell-bench-press.gif', 'gif-binary');

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0025',
            'workoutx_name' => 'barbell-bench-press',
            'query_name' => 'Barbell Bench Press',
            'storage_path' => 'exercises/barbell-bench-press.gif',
            'payload' => [
                'id' => '0025',
                'name' => 'Barbell Bench Press',
                'gifUrl' => 'https://cdn.workoutx.test/barbell-bench-press.gif',
            ],
        ]);

        config()->set('services.workoutx.enabled', true);
        Http::fake();

        $service = new WorkoutMediaService();

        $firstResult = $service->enrichWorkoutPlan([
            'weekly_plan' => [[
                'day' => 'Segunda',
                'focus' => 'Peito',
                'exercises' => [[
                    'name' => 'Supino reto com barra',
                    'category' => 'specific',
                    'sets' => 4,
                    'reps' => '8-12',
                    'rest' => '60s',
                    'notes' => 'Controle o movimento.',
                    'steps' => ['A', 'B'],
                    'remote_exercise_id' => '0025',
                ]],
            ]],
        ]);

        $this->assertSame('Supino reto com barra', data_get($firstResult, 'weekly_plan.0.exercises.0.name'));
        $this->assertDatabaseHas('exercise_media_caches', [
            'remote_exercise_id' => '0025',
            'localized_name_pt_br' => 'Supino reto com barra',
        ]);

        $secondResult = $service->enrichWorkoutPlan([
            'weekly_plan' => [[
                'day' => 'Terca',
                'focus' => 'Peito',
                'exercises' => [[
                    'name' => 'Barbell Bench Press',
                    'category' => 'specific',
                    'sets' => 4,
                    'reps' => '8-12',
                    'rest' => '60s',
                    'notes' => 'Controle o movimento.',
                    'steps' => ['A', 'B'],
                    'remote_exercise_id' => '0025',
                ]],
            ]],
        ]);

        $this->assertSame('Supino reto com barra', data_get($secondResult, 'weekly_plan.0.exercises.0.name'));
        Http::assertNothingSent();
    }

    public function test_lookup_exercise_by_name_downloads_gif_and_persists_cache(): void
    {
        $this->configureIsolatedPublicDisk();

        config()->set('services.workoutx.enabled', true);
        config()->set('services.workoutx.api_base_url', 'https://api.workoutxapp.com/v1');
        config()->set('services.workoutx.api_key', 'secret-from-settings');
        config()->set('services.workoutx.auth_mode', 'header');
        config()->set('services.workoutx.request_timeout', 5);

        Http::fake([
            'https://api.workoutxapp.com/v1/exercises/name/barbell-bench-press' => Http::response([
                'total' => 32,
                'count' => 10,
                'data' => [
                    [
                        'id' => '0025',
                        'name' => 'Barbell Bench Press',
                        'bodyPart' => 'Chest',
                        'gifUrl' => 'https://cdn.workoutx.test/barbell-bench-press.gif',
                    ],
                ],
            ], 200),
            'https://cdn.workoutx.test/barbell-bench-press.gif' => Http::response('gif-binary', 200, [
                'Content-Type' => 'image/gif',
            ]),
        ]);

        $service = new WorkoutMediaService();

        $result = $service->lookupExerciseByName('Barbell Bench Press');

        $this->assertTrue($result['found']);
        $this->assertFalse($result['cached']);
        $this->assertSame('0025', $result['remote_exercise_id']);
        $this->assertSame('barbell-bench-press', $result['workoutx_name']);
        $this->assertSame('Barbell Bench Press', data_get($result, 'exercise.name'));
        $this->assertSame('exercises/barbell-bench-press.gif', data_get($result, 'media.path'));
        $this->assertSame('/storage/exercises/barbell-bench-press.gif', data_get($result, 'media.url'));
        $this->assertDatabaseHas('exercise_media_caches', [
            'workoutx_name' => 'barbell-bench-press',
            'storage_path' => 'exercises/barbell-bench-press.gif',
        ]);
        $this->assertTrue(Storage::disk('public')->exists('exercises/barbell-bench-press.gif'));
    }

    public function test_lookup_exercise_by_name_reuses_cached_record_without_new_http_calls(): void
    {
        $this->configureIsolatedPublicDisk();
        Storage::disk('public')->put('exercises/barbell-bench-press.gif', 'gif-binary');

        \App\Models\Workout\ExerciseMediaCache::query()->create([
            'workoutx_name' => 'barbell-bench-press',
            'query_name' => 'Barbell Bench Press',
            'remote_gif_url' => 'https://cdn.workoutx.test/barbell-bench-press.gif',
            'storage_path' => 'exercises/barbell-bench-press.gif',
            'payload' => [
                'id' => '0025',
                'name' => 'Barbell Bench Press',
            ],
        ]);

        config()->set('services.workoutx.enabled', true);

        Http::fake();

        $service = new WorkoutMediaService();
        $result = $service->lookupExerciseByName('Barbell Bench Press');

        $this->assertTrue($result['found']);
        $this->assertTrue($result['cached']);
        $this->assertSame('', $result['remote_exercise_id']);
        $this->assertSame('/storage/exercises/barbell-bench-press.gif', data_get($result, 'media.url'));
        Http::assertNothingSent();
    }

    public function test_sync_exercise_catalog_paginates_and_updates_existing_records(): void
    {
        config()->set('services.workoutx.enabled', true);
        config()->set('services.workoutx.api_base_url', 'https://api.workoutxapp.com/v1');
        config()->set('services.workoutx.api_key', 'secret-from-settings');
        config()->set('services.workoutx.auth_mode', 'header');
        config()->set('services.workoutx.request_timeout', 5);
        config()->set('services.workoutx.default_limit', 2);

        ExerciseMediaCache::query()->create([
            'workoutx_name' => 'barbell-bench-press',
            'query_name' => 'Barbell Bench Press',
            'remote_gif_url' => 'https://cdn.workoutx.test/old-barbell-bench-press.gif',
            'payload' => [
                'id' => '0025',
                'name' => 'Barbell Bench Press',
            ],
        ]);

        Http::fake([
            'https://api.workoutxapp.com/v1/exercises?limit=2&offset=0' => Http::response([
                [
                    'id' => '0025',
                    'name' => 'Barbell Bench Press',
                    'gifUrl' => 'https://cdn.workoutx.test/barbell-bench-press.gif',
                ],
                [
                    'id' => '0026',
                    'name' => 'Incline Dumbbell Press',
                    'gifUrl' => 'https://cdn.workoutx.test/incline-dumbbell-press.gif',
                ],
            ], 200),
            'https://api.workoutxapp.com/v1/exercises?limit=2&offset=2' => Http::response([
                [
                    'id' => '0027',
                    'name' => 'Cable Fly',
                    'gifUrl' => 'https://cdn.workoutx.test/cable-fly.gif',
                ],
            ], 200),
        ]);

        $service = new WorkoutMediaService();

        $result = $service->syncExerciseCatalog();

        $this->assertSame(3, $result['synced']);
        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['unchanged']);
        $this->assertSame(3, $result['total_cached']);

        $this->assertDatabaseHas('exercise_media_caches', [
            'remote_exercise_id' => '0025',
            'workoutx_name' => 'barbell-bench-press',
            'remote_gif_url' => 'https://cdn.workoutx.test/barbell-bench-press.gif',
        ]);
        $this->assertDatabaseHas('exercise_media_caches', [
            'remote_exercise_id' => '0026',
            'workoutx_name' => 'incline-dumbbell-press',
        ]);
        $this->assertDatabaseHas('exercise_media_caches', [
            'remote_exercise_id' => '0027',
            'workoutx_name' => 'cable-fly',
        ]);

        Http::assertSentCount(2);
        Http::assertSent(static function ($request) {
            return $request->hasHeader('X-WorkoutX-Key', 'secret-from-settings');
        });
    }

    public function test_sync_exercise_catalog_requires_api_key(): void
    {
        config()->set('services.workoutx.enabled', true);
        config()->set('services.workoutx.api_base_url', 'https://api.workoutxapp.com/v1');
        config()->set('services.workoutx.api_key', '');

        $service = new WorkoutMediaService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Defina a API Key da WorkoutX antes de sincronizar o catalogo.');

        $service->syncExerciseCatalog();
    }
}
