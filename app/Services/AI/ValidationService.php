<?php

namespace App\Services\AI;

use App\Models\User;
use App\Services\AI\Validators\BiomechanicalValidator;
use App\Services\AI\Validators\DiversityValidator;
use App\Services\AI\Validators\FatigueValidator;
use App\Services\AI\Validators\ProgressionValidator;
use App\Services\AI\Validators\SafetyValidator;
use App\Services\AI\Validators\StructuralValidator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ValidationService
{
    private array $safetyFlags = [];
    private ?int $expectedTrainingDays = null;

    public function __construct(
        private readonly ?StructuralValidator $structuralValidator = null,
        private readonly ?SafetyValidator $safetyValidator = null,
        private readonly ?BiomechanicalValidator $biomechanicalValidator = null,
        private readonly ?DiversityValidator $diversityValidator = null,
        private readonly ?FatigueValidator $fatigueValidator = null,
        private readonly ?ProgressionValidator $progressionValidator = null,
    ) {}

    public function validateUserForWorkout(User $user): array
    {
        $physicalData = $user->physicalData()->first();
        $medicalData = $user->medicalData()->first();
        $preference = $user->preference()->first();

        if ($physicalData === null || $medicalData === null) {
            throw ValidationException::withMessages([
                'workout' => 'Physical and medical data are required before workout generation.',
            ]);
        }

        $age = $this->resolveAge($user);
        $activityLevel = (string) $physicalData->activity_level;
        $imc = is_numeric($physicalData->imc) ? (float) $physicalData->imc : null;

        $injuriesText = (string) ($medicalData->injuries ?? '');
        $restrictionsText = (string) ($medicalData->restrictions ?? '');

        $flags = [
            'severe_injury' => $this->hasSevereInjury($injuriesText, $restrictionsText),
            'high_imc' => $imc !== null && $imc >= 35,
            'beginner' => in_array($activityLevel, ['sedentary', 'light'], true),
        ];

        $this->safetyFlags = $flags;
        $this->expectedTrainingDays = $this->resolveTrainingDays($preference?->training_frequency);

        return [
            'age' => $age,
            'gender' => $user->gender,
            'height' => $user->height,
            'weight' => $user->weight,
            'imc' => $imc,
            'activity_level' => $activityLevel,
            'goal' => $user->goal,
            'restrictions' => $restrictionsText,
            'injuries' => $injuriesText,
            'flags' => $flags,
        ];
    }

    public function validateWorkoutResponse(array $data, ?array $planningPayload = null): array
    {
        $context = [
            'safety_flags' => $this->safetyFlags,
            'expected_training_days' => $this->expectedTrainingDays,
            'planning_payload' => $planningPayload ?? [],
        ];

        $normalized = ($this->structuralValidator ?? new StructuralValidator())->validate($data, $context);
        ($this->safetyValidator ?? new SafetyValidator())->validate($normalized, $context);
        ($this->biomechanicalValidator ?? new BiomechanicalValidator())->validate($normalized, $context);
        ($this->diversityValidator ?? new DiversityValidator())->validate($normalized, $context);
        ($this->fatigueValidator ?? new FatigueValidator())->validate($normalized, $context);
        ($this->progressionValidator ?? new ProgressionValidator())->validate($normalized, $context);

        return $normalized;
    }

    public function safetyFlags(): array
    {
        return $this->safetyFlags;
    }

    private function resolveTrainingDays(mixed $trainingFrequency): ?int
    {
        $normalized = trim((string) $trainingFrequency);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/(\d{1,2})/', $normalized, $matches) !== 1) {
            return null;
        }

        $days = (int) $matches[1];

        return $days > 0 ? $days : null;
    }

    private function resolveAge(User $user): ?int
    {
        if ($user->birth_date === null) {
            return null;
        }

        return Carbon::parse($user->birth_date)->age;
    }

    private function hasSevereInjury(string $injuries, string $restrictions): bool
    {
        $fullText = mb_strtolower(trim($injuries . ' ' . $restrictions));

        if ($fullText === '') {
            return false;
        }

        $keywords = [
            'grave',
            'fratura',
            'ruptura',
            'hernia',
            'cirurgia',
            'lesao severa',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($fullText, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
