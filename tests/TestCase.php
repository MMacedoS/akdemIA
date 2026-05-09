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
        $user = User::factory()->create(array_merge([
            'birth_date' => '1990-01-01',
            'gender' => 'female',
            'height' => 165,
            'weight' => 60,
            'goal' => 'hipertrofia',
        ], $overrides));

        PhysicalData::query()->create([
            'user_id' => $user->id,
            'activity_level' => 'active',
            'imc' => 22.0,
            'body_fat_percentage' => 18.5,
        ]);

        Preference::query()->create([
            'user_id' => $user->id,
            'preferred_foods' => ['chicken', 'rice', 'broccoli'],
            'disliked_foods' => ['fish', 'tofu'],
            'drinks' => ['water', 'protein shake'],
            'available_hours' => ['all days', 'morning', 'evening'],
            'training_frequency' => 5,
        ]);

        MedicalData::query()->create([
            'user_id' => $user->id,
            'injuries' => 'Nenhuma',
            'diseases' => 'Nenhuma',
            'medications' => 'Nenhuma',
            'restrictions' => 'Nenhuma',
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
