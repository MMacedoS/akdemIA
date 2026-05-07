<?php

namespace Database\Factories\MedicalData;

use App\Models\MedicalData\MedicalData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalData>
 */
class MedicalDataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'tenant_id' => null,
            'blood_pressure' => $this->faker->randomElement(['normal', 'high', 'low']),
            'heart_rate' => $this->faker->numberBetween(60, 100),
            'medical_conditions' => $this->faker->words(3),
        ];
    }
}
