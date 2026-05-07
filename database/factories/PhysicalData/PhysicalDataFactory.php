<?php

namespace Database\Factories\PhysicalData;

use App\Models\PhysicalData\PhysicalData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhysicalData>
 */
class PhysicalDataFactory extends Factory
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
            'activity_level' => $this->faker->randomElement(['sedentary', 'lightly_active', 'active', 'very_active']),
            'imc' => $this->faker->randomFloat(2, 18.5, 40.0),
        ];
    }
}
