<?php

namespace App\DTOs\AI;

final readonly class WorkoutExerciseCandidate
{
    public function __construct(
        public string $remoteExerciseId,
        public string $localizedNamePtBr,
        public string $workoutxName,
        public string $focus,
        public string $bodyPart,
        public string $target,
        public string $equipment,
    ) {}

    public function toArray(): array
    {
        return [
            'remote_exercise_id' => $this->remoteExerciseId,
            'name' => $this->localizedNamePtBr,
            'workoutx_name' => $this->workoutxName,
            'focus' => $this->focus,
            'body_part' => $this->bodyPart,
            'target' => $this->target,
            'equipment' => $this->equipment,
        ];
    }
}
