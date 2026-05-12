<?php

namespace App\DTOs\AI;

final readonly class WorkoutRetrievalResult
{
    /**
     * @param  array<int, WorkoutExerciseCandidate>  $candidates
     */
    public function __construct(
        public array $candidates,
        public string $mode,
        public string $query,
        public ?string $vectorStoreId,
        public ?string $fileId,
        public array $metadata = [],
    ) {}

    public function compactCandidates(): array
    {
        return array_map(
            static fn(WorkoutExerciseCandidate $candidate): array => $candidate->toArray(),
            $this->candidates,
        );
    }
}