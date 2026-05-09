<?php

namespace Tests\Feature\Web\V1\SystemAdmin;

use App\Enums\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutRulesSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_update_workout_rules(): void
    {
        $user = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
            'is_system_admin' => true,
        ]);

        $response = $this->actingAs($user)
            ->put(route('system-admin.settings.workouts.update'), [
                'workout_generate_cost' => 5,
                'workout_reuse_cost' => 3,
                'workout_reactivate_cost' => 2,
                'workout_active_days' => 60,
            ]);

        $response->assertRedirect(route('system-admin.settings.workouts.edit'));

        $this->assertSame('5', SystemSetting::query()->where('key', 'workout.generate_cost')->value('value'));
        $this->assertSame('3', SystemSetting::query()->where('key', 'workout.reuse_cost')->value('value'));
        $this->assertSame('2', SystemSetting::query()->where('key', 'workout.reactivate_cost')->value('value'));
        $this->assertSame('60', SystemSetting::query()->where('key', 'workout.active_days')->value('value'));
    }
}
