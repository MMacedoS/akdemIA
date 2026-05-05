<?php

namespace Tests\Unit\Services\Workouts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Workouts\WorkoutMediaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        $this->assertSame('/storage/exercises/barbell-bench-press.gif', data_get($result, 'media.url'));
        Http::assertNothingSent();
    }
}
