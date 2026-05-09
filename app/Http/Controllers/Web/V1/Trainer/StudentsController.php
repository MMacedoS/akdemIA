<?php

namespace App\Http\Controllers\Web\V1\Trainer;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateWorkoutJob;
use App\Models\MedicalData\MedicalData;
use App\Models\PhysicalData\PhysicalData;
use App\Models\Preferences\Preference;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use App\Repositories\Contracts\Tenant\TraineeStudentRepositoryContract;
use App\Services\Credits\CreditService;
use App\Services\Workouts\ExerciseCatalogService;
use App\Services\Workouts\WorkoutLifecycleService;
use App\Services\Workouts\WorkoutMediaService;
use App\Services\Workouts\WorkoutRulesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class StudentsController extends Controller
{
    public function __construct(
        private readonly TraineeStudentRepositoryContract $repository,
        private readonly CreditService $creditService,
        private readonly ExerciseCatalogService $exerciseCatalogService,
        private readonly WorkoutMediaService $workoutMediaService,
        private readonly WorkoutRulesService $workoutRulesService,
        private readonly WorkoutLifecycleService $workoutLifecycleService,
    ) {}

    public function index(Request $request): View
    {
        $trainer = $this->resolveTrainer($request);
        $search = trim((string) $request->query('q', ''));

        return view('web.v1.trainer.students.index', [
            'students' => $this->repository->paginateForTrainee(null, $trainer->id, $search),
            'search' => $search,
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $student = $this->resolveStudent($request, $id);
        $student->loadMissing(['physicalData', 'medicalData', 'preference']);

        $this->workoutLifecycleService->expireExpiredWorkouts(null, $student->id);

        $workouts = $this->studentWorkoutQuery($student)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'status', 'request_status', 'created_at']);

        return view('web.v1.trainer.students.show', [
            'student' => $student,
            'workouts' => $workouts,
        ]);
    }

    public function generateWorkout(Request $request, int $id): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);
        $trainer = $this->resolveTrainer($request);
        $student = $this->resolveStudent($request, $id);

        $payload = $request->validate([
            'adjustment_request' => ['nullable', 'string', 'max:1500'],
        ]);

        $adjustmentRequest = trim((string) ($payload['adjustment_request'] ?? ''));
        $normalizedAdjustmentRequest = $adjustmentRequest !== '' ? $adjustmentRequest : null;

        $hasProcessingWorkout = $this->studentWorkoutQuery($student)
            ->where('status', 'processing')
            ->exists();

        if ($hasProcessingWorkout) {
            return redirect()->route('trainer.students.show', $student->id)
                ->withErrors(['workout' => 'Ja existe uma geracao em processamento para este aluno. Aguarde finalizar para evitar novo consumo de credito.']);
        }

        try {
            $this->creditService->consumeCredits(
                $trainer,
                $this->workoutRulesService->generationCredits(),
                'consume_generation',
                [
                    'context' => 'web_trainer',
                    'tenant_id' => $tenant->id,
                    'trainer_id' => (int) $trainer->id,
                    'student_id' => $student->id,
                ],
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('trainer.students.show', $student->id)
                ->withErrors(['workout' => $exception->getMessage()]);
        }

        $workout = Workout::query()->create(array_merge([
            'tenant_id' => null,
            'user_id' => $student->id,
            'status' => 'processing',
            'workout_plan' => ['weekly_plan' => []],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ], $this->workoutLifecycleService->activeAttributes()));

        GenerateWorkoutJob::dispatch($workout->id, $student->id, null, $normalizedAdjustmentRequest, (int) $trainer->id);

        return redirect()->route('trainer.students.show', $student->id)
            ->with('status', 'Geracao de treino com ilustracoes e recomendacoes iniciada. Saldo atual: ' . (int) $trainer->fresh()?->credits_balance . ' credito(s).');
    }

    public function regenerateWorkout(Request $request, int $id, int $workoutId): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);
        $trainer = $this->resolveTrainer($request);
        $student = $this->resolveStudent($request, $id);

        $payload = $request->validate([
            'adjustment_request' => ['nullable', 'string', 'max:1500'],
        ]);

        $adjustmentRequest = trim((string) ($payload['adjustment_request'] ?? ''));
        $normalizedAdjustmentRequest = $adjustmentRequest !== '' ? $adjustmentRequest : null;

        $hasProcessingWorkout = $this->studentWorkoutQuery($student)
            ->where('status', 'processing')
            ->exists();

        if ($hasProcessingWorkout) {
            return redirect()->route('trainer.students.workouts.show', [$student->id, $workoutId])
                ->withErrors(['workout' => 'Ja existe uma geracao em processamento para este aluno. Aguarde finalizar para evitar novo consumo de credito.']);
        }

        $targetWorkout = $this->studentWorkoutQuery($student)
            ->where('id', $workoutId)
            ->firstOrFail();

        $targetWorkout = $this->workoutLifecycleService->syncWorkoutStatus($targetWorkout);

        if ((string) ($targetWorkout->request_status ?? 'active') !== 'active') {
            return redirect()->route('trainer.students.workouts.show', [$student->id, $targetWorkout->id])
                ->withErrors(['workout' => 'Treino inativo. Nao e permitido refazer este plano.']);
        }

        if ((string) $targetWorkout->status !== 'done') {
            return redirect()->route('trainer.students.workouts.show', [$student->id, $targetWorkout->id])
                ->withErrors(['workout' => 'Aguarde a conclusao do treino antes de refazer.']);
        }

        try {
            $this->creditService->consumeCredits(
                $trainer,
                $this->workoutRulesService->reuseCredits(),
                'consume_regeneration',
                [
                    'context' => 'web_trainer',
                    'tenant_id' => $tenant->id,
                    'trainer_id' => (int) $trainer->id,
                    'student_id' => $student->id,
                    'source_workout_id' => $targetWorkout->id,
                ],
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('trainer.students.workouts.show', [$student->id, $targetWorkout->id])
                ->withErrors(['workout' => $exception->getMessage()]);
        }

        $targetWorkout->fill([
            'request_status' => 'inactive',
        ]);
        $targetWorkout->save();

        $newWorkout = Workout::query()->create(array_merge([
            'tenant_id' => null,
            'user_id' => $student->id,
            'status' => 'processing',
            'regeneration_request' => $normalizedAdjustmentRequest,
            'workout_plan' => ['weekly_plan' => []],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ], $this->workoutLifecycleService->activeAttributes()));

        GenerateWorkoutJob::dispatch($newWorkout->id, $student->id, null, $normalizedAdjustmentRequest, (int) $trainer->id);

        return redirect()->route('trainer.students.workouts.show', [$student->id, $newWorkout->id])
            ->with('status', 'Refazer treino iniciado com as instrucoes enviadas para a IA. Saldo atual: ' . (int) $trainer->fresh()?->credits_balance . ' credito(s).');
    }

    public function showWorkout(Request $request, int $id, int $workoutId): View
    {
        $student = $this->resolveStudent($request, $id);

        $workout = $this->studentWorkoutQuery($student)
            ->where('id', $workoutId)
            ->firstOrFail();

        $workout = $this->workoutLifecycleService->syncWorkoutStatus($workout);

        return view('web.v1.trainer.students.workouts.show', [
            'student' => $student,
            'workout' => $this->hydrateWorkoutMedia($workout),
        ]);
    }

    public function searchWorkoutCatalog(Request $request, int $id): JsonResponse
    {
        $this->resolveStudent($request, $id);

        $result = $this->exerciseCatalogService->listForInternalApi(
            focus: $request->query('focus'),
            search: $request->query('search'),
            translationStatus: null,
            limit: (int) $request->query('limit', 10),
            offset: 0,
        );

        return response()->json($result);
    }

    public function retryWorkout(Request $request, int $id, int $workoutId): RedirectResponse
    {
        $trainer = $this->resolveTrainer($request);
        $student = $this->resolveStudent($request, $id);

        $targetWorkout = $this->studentWorkoutQuery($student)
            ->where('id', $workoutId)
            ->firstOrFail();

        if ((string) $targetWorkout->status !== 'error') {
            return redirect()->route('trainer.students.workouts.show', [$student->id, $targetWorkout->id])
                ->withErrors(['workout' => 'O reenvio esta disponivel apenas para treinos com falha.']);
        }

        $hasProcessingWorkout = $this->studentWorkoutQuery($student)
            ->where('status', 'processing')
            ->where('id', '!=', $targetWorkout->id)
            ->exists();

        if ($hasProcessingWorkout) {
            return redirect()->route('trainer.students.workouts.show', [$student->id, $targetWorkout->id])
                ->withErrors(['workout' => 'Ja existe outra geracao em processamento para este aluno.']);
        }

        $targetWorkout->fill([
            'status' => 'processing',
            'safety_flags' => [],
        ]);
        $targetWorkout->save();

        GenerateWorkoutJob::dispatch(
            $targetWorkout->id,
            $student->id,
            null,
            $targetWorkout->regeneration_request ? (string) $targetWorkout->regeneration_request : null,
            (int) $trainer->id,
        );

        return redirect()->route('trainer.students.workouts.show', [$student->id, $targetWorkout->id])
            ->with('status', 'Transacao reenviada para processamento. Nao houve novo consumo de credito.');
    }

    public function activateWorkout(Request $request, int $id, int $workoutId): RedirectResponse
    {
        $student = $this->resolveStudent($request, $id);
        $trainer = $this->resolveTrainer($request);
        $tenant = $this->resolveTenant($request);

        $workout = $this->studentWorkoutQuery($student)
            ->where('id', $workoutId)
            ->firstOrFail();

        $workout = $this->workoutLifecycleService->syncWorkoutStatus($workout);

        if ((string) $workout->request_status === 'active') {
            return redirect()->route('trainer.students.workouts.show', [$student->id, $workout->id])
                ->with('status', 'Treino ja esta ativo.');
        }

        try {
            $this->creditService->consumeCredits(
                $trainer,
                $this->workoutRulesService->reactivationCredits(),
                'consume_reactivation',
                [
                    'context' => 'web_trainer',
                    'tenant_id' => $tenant->id,
                    'trainer_id' => (int) $trainer->id,
                    'student_id' => $student->id,
                    'workout_id' => $workout->id,
                ],
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('trainer.students.workouts.show', [$student->id, $workout->id])
                ->withErrors(['workout' => $exception->getMessage()]);
        }

        $this->workoutLifecycleService->activateWorkout($this->studentWorkoutQuery($student), $workout);

        return redirect()->route('trainer.students.workouts.show', [$student->id, $workout->id])
            ->with('status', 'Treino ativado com sucesso. Saldo atual: ' . (int) $trainer->fresh()?->credits_balance . ' credito(s).');
    }

    public function inactivateWorkout(Request $request, int $id, int $workoutId): RedirectResponse
    {
        $student = $this->resolveStudent($request, $id);

        $workout = $this->studentWorkoutQuery($student)
            ->where('id', $workoutId)
            ->firstOrFail();

        $this->workoutLifecycleService->inactivateWorkout($workout);

        return redirect()->route('trainer.students.workouts.show', [$student->id, $workout->id])
            ->with('status', 'Treino inativado com sucesso.');
    }

    public function reuseWorkout(Request $request, int $id, int $workoutId): RedirectResponse
    {
        $student = $this->resolveStudent($request, $id);

        $sourceWorkout = $this->studentWorkoutQuery($student)
            ->where('id', $workoutId)
            ->firstOrFail();

        if ((string) $sourceWorkout->status !== 'done') {
            return redirect()->route('trainer.students.workouts.show', [$student->id, $sourceWorkout->id])
                ->withErrors(['workout' => 'Somente treino concluido pode ser reaproveitado.']);
        }

        try {
            $this->creditService->consumeCredits(
                $this->resolveTrainer($request),
                $this->workoutRulesService->reuseCredits(),
                'consume_regeneration',
                [
                    'context' => 'web_trainer',
                    'tenant_id' => $this->resolveTenant($request)->id,
                    'trainer_id' => (int) $request->user()?->id,
                    'student_id' => $student->id,
                    'source_workout_id' => $sourceWorkout->id,
                    'mode' => 'manual_reuse',
                ],
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('trainer.students.workouts.show', [$student->id, $sourceWorkout->id])
                ->withErrors(['workout' => $exception->getMessage()]);
        }

        $this->studentWorkoutQuery($student)
            ->where('request_status', 'active')
            ->update(['request_status' => 'inactive']);

        $newWorkout = Workout::query()->create(array_merge([
            'tenant_id' => null,
            'user_id' => $student->id,
            'status' => 'done',
            'regeneration_request' => 'Treino reaproveitado manualmente sem chamada de IA.',
            'workout_plan' => $sourceWorkout->workout_plan ?? ['weekly_plan' => []],
            'meal_plan' => $sourceWorkout->meal_plan ?? [],
            'recommendations' => $sourceWorkout->recommendations ?? [],
            'cardio_plan' => $sourceWorkout->cardio_plan ?? [],
            'safety_flags' => $sourceWorkout->safety_flags ?? [],
        ], $this->workoutLifecycleService->activeAttributes()));

        return redirect()->route('trainer.students.workouts.show', [$student->id, $newWorkout->id])
            ->with('status', 'Treino reaproveitado. Agora voce pode editar e reorganizar os exercicios no board.');
    }

    public function updateWorkoutBoard(Request $request, int $id, int $workoutId): RedirectResponse
    {
        $student = $this->resolveStudent($request, $id);

        $workout = $this->studentWorkoutQuery($student)
            ->where('id', $workoutId)
            ->firstOrFail();

        $workout = $this->workoutLifecycleService->syncWorkoutStatus($workout);

        if ((string) ($workout->request_status ?? 'active') !== 'active') {
            return redirect()->route('trainer.students.workouts.show', [$student->id, $workout->id])
                ->withErrors(['workout' => 'Treino inativo. Nao e permitido editar este plano.']);
        }

        $payload = $request->validate([
            'weekly_plan' => ['required', 'string'],
        ]);

        $decodedPlan = json_decode((string) $payload['weekly_plan'], true);

        if (! is_array($decodedPlan) || $decodedPlan === []) {
            return redirect()->route('trainer.students.workouts.show', [$student->id, $workout->id])
                ->withErrors(['workout' => 'Plano semanal invalido para salvar.']);
        }

        $normalizedPlan = $this->normalizeManualWeeklyPlan($decodedPlan);

        if ($normalizedPlan === []) {
            return redirect()->route('trainer.students.workouts.show', [$student->id, $workout->id])
                ->withErrors(['workout' => 'Nenhum dia valido encontrado no plano para salvar.']);
        }

        $workout->fill([
            'status' => 'done',
            'workout_plan' => $this->workoutMediaService->enrichWorkoutPlan(['weekly_plan' => $normalizedPlan]),
        ]);
        $workout->save();

        return redirect()->route('trainer.students.workouts.show', [$student->id, $workout->id])
            ->with('status', 'Treino atualizado com sucesso pelo board manual.');
    }

    public function edit(Request $request, int $id): View
    {
        $student = $this->resolveStudent($request, $id);
        $student->loadMissing(['physicalData', 'medicalData', 'preference']);

        return view('web.v1.trainer.students.edit', [
            'student' => $student,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $student = $this->resolveStudent($request, $id);

        $payload = $request->validate([
            'body_fat_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'activity_level' => ['nullable', 'string', 'max:255'],
            'imc' => ['nullable', 'numeric', 'between:0,100'],
            'injuries' => ['nullable', 'string', 'max:1200'],
            'diseases' => ['nullable', 'string', 'max:1200'],
            'medications' => ['nullable', 'string', 'max:1200'],
            'restrictions' => ['nullable', 'string', 'max:1200'],
            'preferred_foods' => ['nullable', 'string', 'max:2000'],
            'disliked_foods' => ['nullable', 'string', 'max:2000'],
            'drinks' => ['nullable', 'string', 'max:2000'],
            'available_hours' => ['nullable', 'string', 'max:2000'],
            'training_frequency' => ['nullable', 'string', 'max:255'],
        ]);

        PhysicalData::query()->updateOrCreate(
            ['user_id' => $student->id],
            [
                'body_fat_percentage' => $payload['body_fat_percentage'] ?? null,
                'activity_level' => $payload['activity_level'] ?? null,
                'imc' => $payload['imc'] ?? null,
            ]
        );

        MedicalData::query()->updateOrCreate(
            ['user_id' => $student->id],
            [
                'injuries' => $payload['injuries'] ?? null,
                'diseases' => $payload['diseases'] ?? null,
                'medications' => $payload['medications'] ?? null,
                'restrictions' => $payload['restrictions'] ?? null,
            ]
        );

        Preference::query()->updateOrCreate(
            ['user_id' => $student->id],
            [
                'preferred_foods' => $this->parseCsvToArray($payload['preferred_foods'] ?? null),
                'disliked_foods' => $this->parseCsvToArray($payload['disliked_foods'] ?? null),
                'drinks' => $this->parseCsvToArray($payload['drinks'] ?? null),
                'available_hours' => $this->parseCsvToArray($payload['available_hours'] ?? null),
                'training_frequency' => $payload['training_frequency'] ?? null,
            ]
        );

        return redirect()->route('trainer.students.show', $student->id)
            ->with('status', 'Dados fisicos, medicos e preferencias atualizados.');
    }

    private function resolveTenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            abort(409, 'Tenant not identified.');
        }

        return $tenant;
    }

    private function resolveTrainer(Request $request): User
    {
        $trainer = $request->user();

        abort_unless($trainer instanceof User, 401, 'Sessao invalida. Faca login novamente.');

        return $trainer;
    }

    private function resolveStudent(Request $request, int $studentId): User
    {
        $this->resolveTenant($request);
        $trainer = $this->resolveTrainer($request);

        return $this->repository->findForTrainee(null, $trainer->id, $studentId);
    }

    private function studentWorkoutQuery(User $student)
    {
        return Workout::query()
            ->whereNull('tenant_id')
            ->where('user_id', $student->id);
    }

    private function parseCsvToArray(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn($item) => trim($item))
            ->filter(fn($item) => $item !== '')
            ->values()
            ->all();
    }

    private function normalizeManualWeeklyPlan(array $weeklyPlan): array
    {
        $normalizedDays = [];

        foreach ($weeklyPlan as $dayIndex => $dayPlan) {
            if (! is_array($dayPlan)) {
                continue;
            }

            $dayName = trim((string) ($dayPlan['day'] ?? 'Dia ' . ($dayIndex + 1)));
            $focus = trim((string) ($dayPlan['focus'] ?? 'Treino geral'));
            $rawExercises = $dayPlan['exercises'] ?? [];

            if (! is_array($rawExercises) || $rawExercises === []) {
                continue;
            }

            $normalizedExercises = [];

            foreach ($rawExercises as $exercise) {
                if (! is_array($exercise)) {
                    continue;
                }

                $name = trim((string) ($exercise['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $sets = (int) ($exercise['sets'] ?? 3);
                if ($sets <= 0) {
                    $sets = 1;
                }

                if ($sets > 10) {
                    $sets = 10;
                }

                $category = mb_strtolower(trim((string) ($exercise['category'] ?? 'specific')));
                if (! in_array($category, ['specific', 'cardio'], true)) {
                    $category = 'specific';
                }

                $notes = trim((string) ($exercise['notes'] ?? ''));
                $steps = collect(is_array($exercise['steps'] ?? null) ? $exercise['steps'] : [])
                    ->map(fn(mixed $step): string => trim((string) $step))
                    ->filter()
                    ->values()
                    ->take(5)
                    ->all();

                $normalizedExercises[] = [
                    'name' => $name,
                    'category' => $category,
                    'sets' => $sets,
                    'reps' => trim((string) ($exercise['reps'] ?? '10-12')),
                    'rest' => trim((string) ($exercise['rest'] ?? '60s')),
                    'notes' => $notes,
                    'steps' => $steps,
                    'remote_exercise_id' => trim((string) ($exercise['remote_exercise_id'] ?? '')),
                    'workoutx_name' => $this->workoutMediaService->normalizeWorkoutxName(
                        $exercise['workoutx_name'] ?? data_get($exercise, 'workoutx_lookup.name'),
                        $name,
                    ),
                    'exercise_media_path' => trim((string) ($exercise['exercise_media_path'] ?? '')),
                    'exercise_media_url' => trim((string) ($exercise['exercise_media_url'] ?? '')),
                ];
            }

            if ($normalizedExercises === []) {
                continue;
            }

            $normalizedDays[] = [
                'day' => $dayName === '' ? 'Dia ' . ($dayIndex + 1) : $dayName,
                'focus' => $focus === '' ? 'Treino geral' : $focus,
                'exercises' => $normalizedExercises,
            ];
        }

        return $normalizedDays;
    }

    private function hydrateWorkoutMedia(Workout $workout): Workout
    {
        $workoutPlan = $workout->workout_plan;

        if (! is_array($workoutPlan)) {
            return $workout;
        }

        if (! $this->workoutMediaService->workoutPlanNeedsMediaRefresh($workoutPlan)) {
            return $workout;
        }

        $workout->workout_plan = $this->workoutMediaService->enrichWorkoutPlan($workoutPlan);
        $workout->save();

        return $workout->fresh() ?? $workout;
    }
}
