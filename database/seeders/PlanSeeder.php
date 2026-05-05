<?php

namespace Database\Seeders;

use App\Models\Tenant\Plan;
use Illuminate\Database\Seeder;
use RuntimeException;

class PlanSeeder extends Seeder
{
    private const ESTIMATED_AI_COST_PER_REQUEST = 0.03;
    private const TARGET_MARGIN = 0.70;

    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basico',
                'price' => 39.90,
                'max_students' => 50,
                'max_trainers' => 2,
                'ai_limit' => 100,
                'features' => [
                    'tier' => 'basic',
                    'unlimited_students' => false,
                    'unlimited_trainers' => false,
                    'ai_tier' => 'standard',
                ],
            ],
            [
                'name' => 'Profissional',
                'price' => 149.90,
                'max_students' => 200,
                'max_trainers' => 5,
                'ai_limit' => 800,
                'features' => [
                    'tier' => 'professional',
                    'unlimited_students' => false,
                    'unlimited_trainers' => false,
                    'ai_tier' => 'high',
                ],
            ],
            [
                'name' => 'Premium',
                'price' => 2999.90,
                'max_students' => 100000,
                'max_trainers' => 100000,
                'ai_limit' => 20000,
                'features' => [
                    'tier' => 'premium',
                    'unlimited_students' => true,
                    'unlimited_trainers' => true,
                    'ai_tier' => 'very_high',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            $this->assertMargin((float) $plan['price'], (int) $plan['ai_limit'], (string) $plan['name']);

            Plan::query()->updateOrCreate(
                ['name' => $plan['name']],
                [
                    'price' => $plan['price'],
                    'max_students' => $plan['max_students'],
                    'max_trainers' => $plan['max_trainers'],
                    'ai_limit' => $plan['ai_limit'],
                    'features' => $plan['features'],
                ],
            );
        }
    }

    private function assertMargin(float $price, int $aiLimit, string $planName): void
    {
        $estimatedAiCost = $aiLimit * self::ESTIMATED_AI_COST_PER_REQUEST;
        $minimumPrice = $estimatedAiCost / (1 - self::TARGET_MARGIN);

        if ($price < $minimumPrice) {
            throw new RuntimeException("Plan {$planName} does not meet target AI margin.");
        }
    }
}
