<?php

namespace Tests\Feature\Web\V1\SystemAdmin;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workout\ExerciseMediaCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutxAuditViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_audit_page_shows_local_gif_preview_and_storage_path(): void
    {
        $user = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
            'is_system_admin' => true,
        ]);

        ExerciseMediaCache::query()->create([
            'remote_exercise_id' => '0001',
            'localized_name_pt_br' => 'Supino reto',
            'workoutx_name' => 'barbell-bench-press',
            'query_name' => 'Barbell Bench Press',
            'remote_gif_url' => 'https://cdn.workoutx.test/barbell-bench-press.gif',
            'storage_path' => 'exercises/barbell-bench-press.gif',
            'payload' => [
                'id' => '0001',
                'name' => 'Barbell Bench Press',
                'bodyPart' => 'chest',
                'target' => 'pectorals',
                'equipment' => 'barbell',
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('system-admin.settings.workoutx.audit'));

        $response->assertOk();
        $response->assertSee('Baixar GIFs pendentes');
        $response->assertSee('Salvo local');
        $response->assertSee('exercises/barbell-bench-press.gif');
        $response->assertSee('storage/exercises/barbell-bench-press.gif');
    }
}
