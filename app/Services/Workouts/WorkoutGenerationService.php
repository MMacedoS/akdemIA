<?php

namespace App\Services\Workouts;

use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use App\Services\AI\AiService;
use App\Services\AI\ValidationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class WorkoutGenerationService
{
    private const CACHE_VERSION = 'v4-workoutx-media-refresh';
    private const WORKOUTX_CACHE_BUSTER_KEY = 'workoutx:cache_buster';

    public function __construct(
        private readonly ValidationService $validationService,
        private readonly AiService $aiService,
        private readonly WorkoutMediaService $workoutMediaService,
    ) {}

    public function generate(User $user, ?Tenant $tenant, ?string $adjustmentRequest = null): Workout
    {
        if ($tenant instanceof Tenant && ! $user->belongsToTenant($tenant)) {
            throw new AuthorizationException('Forbidden for tenant context.');
        }

        $normalizedAdjustmentRequest = trim((string) $adjustmentRequest);

        if ($normalizedAdjustmentRequest !== '') {
            return $this->generateAndStore($user, $tenant, $normalizedAdjustmentRequest);
        }

        return $this->generateAndStore($user, $tenant);
    }

    private function generateAndStore(User $user, ?Tenant $tenant, ?string $adjustmentRequest = null): Workout
    {
        if ($tenant instanceof Tenant && ! $user->belongsToTenant($tenant)) {
            throw new AuthorizationException('Forbidden for tenant context.');
        }

        $this->validationService->validateUserForWorkout($user);
        $wellbeingResponse = $this->aiService->generateRecommendations($user, $tenant);
        $safeWorkoutData = null;
        $acceptedWorkoutResponse = null;
        $lastValidationException = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $useConservativePrompt = $attempt > 1;

            $workoutResponse = $this->aiService->generateWorkout(
                $user,
                $tenant,
                $useConservativePrompt,
                $adjustmentRequest,
            );

            try {
                $safeWorkoutData = $this->validationService->validateWorkoutResponse($workoutResponse);
                $acceptedWorkoutResponse = $workoutResponse;
                break;
            } catch (ValidationException $validationException) {
                $lastValidationException = $validationException;
            }
        }

        if (! is_array($safeWorkoutData) || ! is_array($acceptedWorkoutResponse)) {
            throw $lastValidationException ?? ValidationException::withMessages([
                'workout' => 'Unable to generate a safe workout response.',
            ]);
        }

        $workoutPlan = $this->workoutMediaService->enrichWorkoutPlan($safeWorkoutData);

        return Workout::query()->create([
            'tenant_id' => $tenant?->id,
            'user_id' => $user->id,
            'workout_plan' => $workoutPlan,
            'meal_plan' => [],
            'recommendations' => $wellbeingResponse['recommendations'] ?? [],
            'cardio_plan' => $wellbeingResponse['cardio_plan'] ?? [],
            'safety_flags' => $this->validationService->safetyFlags(),
        ]);
    }

    private function buildCacheKey(User $user, ?Tenant $tenant): string
    {
        $physicalDataUpdatedAt = $user->physicalData()->first()?->updated_at?->timestamp ?? 0;
        $medicalDataUpdatedAt = $user->medicalData()->first()?->updated_at?->timestamp ?? 0;
        $preferenceUpdatedAt = $user->preference()->first()?->updated_at?->timestamp ?? 0;
        $userUpdatedAt = $user->updated_at?->timestamp ?? 0;
        $workoutxCacheBuster = (int) Cache::get(self::WORKOUTX_CACHE_BUSTER_KEY, 0);
        $workoutxConfigMarker = implode('-', [
            config('services.workoutx.enabled') ? '1' : '0',
            config('services.workoutx.allow_fallback') ? '1' : '0',
            (string) config('services.workoutx.auth_mode', 'header'),
            substr(sha1((string) config('services.workoutx.api_base_url', '')), 0, 8),
            substr(sha1($this->aiService->workoutPromptVersion()), 0, 8),
            $workoutxCacheBuster,
        ]);

        $updatedMarker = implode('-', [
            $userUpdatedAt,
            $physicalDataUpdatedAt,
            $medicalDataUpdatedAt,
            $preferenceUpdatedAt,
        ]);

        return 'workout:' . self::CACHE_VERSION . ':' . $user->id . ':' . $updatedMarker . ':workoutx:' . $workoutxConfigMarker . ':tenant:' . ($tenant?->id ?? 'none');
    }
}
