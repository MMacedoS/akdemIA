<?php

namespace Database\Factories\Preferences;

use App\Models\Preferences\Preference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Preference>
 */
class PreferenceFactory extends Factory
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
            'preferred_foods' => $this->faker->words(3),
            'disliked_foods' => $this->faker->words(2),
            'drinks' => $this->faker->words(2),
            'available_hours' => ['morning', 'evening'],
            'training_frequency' => $this->faker->numberBetween(1, 7),
        ];
    }
}
