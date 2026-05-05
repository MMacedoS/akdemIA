<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentHealthUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_store_textual_training_frequency(): void
    {
        $user = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
        ]);

        $response = $this
            ->actingAs($user)
            ->put(route('students.health.update'), [
                'preferred_foods' => 'frango',
                'disliked_foods' => 'pimenta',
                'drinks' => 'agua, suco',
                'available_hours' => 'seg 05h, ter 05h, qua 05h, qui 05h, sex 05h',
                'training_frequency' => '5x por semana',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('students.health.edit'));

        $this->assertDatabaseHas('preferences', [
            'user_id' => $user->id,
            'training_frequency' => '5x por semana',
        ]);
    }
}
