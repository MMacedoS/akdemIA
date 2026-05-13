<?php

namespace App\Services\Workout\Planning;

use App\DTOs\AI\WorkoutGenerationContext;
use App\DTOs\AI\WorkoutRetrievalResult;

class WorkoutPlanningEngine
{
    public function __construct(
        private readonly UserTrainingMemoryService $trainingMemoryService,
        private readonly SplitStrategyService $splitStrategyService,
        private readonly VolumeDistributionService $volumeDistributionService,
        private readonly BiomechanicalBalancer $biomechanicalBalancer,
        private readonly FatigueManagementService $fatigueManagementService,
        private readonly ExerciseSelectionEngine $exerciseSelectionEngine,
        private readonly WorkoutQualityScoringService $qualityScoringService,
    ) {}

    public function plan(WorkoutGenerationContext $context, WorkoutRetrievalResult $retrieval): array
    {
        $trainingMemory = $this->trainingMemoryService->build($context);
        $splitPlan = $this->splitStrategyService->build($context, $trainingMemory);
        $volumeDistribution = $this->volumeDistributionService->build($context, $splitPlan, $trainingMemory);
        $balancedSplit = $this->biomechanicalBalancer->balance($splitPlan, $volumeDistribution);
        $fatiguePlan = $this->fatigueManagementService->build($context, $balancedSplit, $trainingMemory);
        $selectedDays = $this->exerciseSelectionEngine->select(
            $context,
            $retrieval,
            $balancedSplit,
            $volumeDistribution,
            $fatiguePlan,
            $trainingMemory,
        );
        $qualityScores = $this->qualityScoringService->score($selectedDays, $trainingMemory, $fatiguePlan);

        return [
            'weekly_frequency' => $balancedSplit['weekly_frequency'],
            'split' => $balancedSplit['split'],
            'volume_distribution' => $volumeDistribution,
            'training_memory' => $trainingMemory,
            'fatigue_management' => $fatiguePlan,
            'selected_days' => $selectedDays,
            'quality_scores' => $qualityScores,
        ];
    }
}
