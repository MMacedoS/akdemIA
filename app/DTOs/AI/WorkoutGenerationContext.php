<?php

namespace App\DTOs\AI;

final readonly class WorkoutGenerationContext
{
    public function __construct(
        public int $userId,
        public ?int $tenantId,
        public array $profile,
        public array $previousWorkoutPlan,
        public bool $conservativeMode,
        public ?string $adjustmentRequest,
        public ?int $expectedTrainingDays,
    ) {}

    public function promptFingerprint(): string
    {
        return hash('sha256', json_encode([
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'profile' => $this->profile,
            'previous_workout_plan' => $this->previousWorkoutPlan,
            'conservative_mode' => $this->conservativeMode,
            'adjustment_request' => $this->adjustmentRequest,
            'expected_training_days' => $this->expectedTrainingDays,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function retrievalQuery(): string
    {
        return implode(' | ', array_filter([
            'objetivo: ' . (string) ($this->profile['goal'] ?? 'N/A'),
            'nivel: ' . (string) ($this->profile['activity_level'] ?? 'N/A'),
            'frequencia: ' . (string) ($this->profile['training_frequency'] ?? 'N/A'),
            'restricoes: ' . (string) ($this->profile['restrictions'] ?? 'Nenhuma'),
            'lesoes: ' . (string) ($this->profile['injuries'] ?? 'Nenhuma'),
            $this->adjustmentRequest !== null && $this->adjustmentRequest !== ''
                ? 'ajuste: ' . $this->adjustmentRequest
                : null,
        ]));
    }
}