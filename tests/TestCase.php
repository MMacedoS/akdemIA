<?php

namespace Tests;

use App\Models\MedicalData\MedicalData;
use App\Models\PhysicalData\PhysicalData;
use App\Models\Preferences\Preference;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    public function mockCreateUserTotal(array $overrides = []): User
    {
        $user = User::factory()->make([
            'birth_date' => '1990-01-01',
            'gender' => 'female',
            'height' => 165,
            'weight' => 60,
            'goal' => 'hipertrofia',
        ]);

        PhysicalData::Factory()->make([
            'user_id' => $user->id,
            'tenant_id' => null,
            'activity_level' => 'active',
            'imc' => 22.0,
        ]);

        Preference::Factory()->make([
            'user_id' => $user->id,
            'preferred_foods' => ['chicken', 'rice', 'broccoli'],
            'disliked_foods' => ['fish', 'tofu'],
            'drinks' => ['water', 'protein shake'],
            'available_hours' => ['all days', 'morning', 'evening'],
            'training_frequency' => 5,
        ]);

        MedicalData::Factory()->make([
            'user_id' => $user->id,
            'tenant_id' => null,
            'blood_pressure' => 'normal',
            'heart_rate' => 70,
            'medical_conditions' => ['none'],
        ]);

        return $user;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
