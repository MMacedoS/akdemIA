<?php

namespace App\Http\Controllers\Web\V1\Trainee;

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
use App\Services\Workouts\WorkoutGenerationCooldownService;
use App\Services\Workouts\WorkoutInsightsService;
use App\Services\Workouts\WorkoutLifecycleService;
use App\Services\Workouts\WorkoutMediaService;
use App\Services\Workouts\WorkoutRulesService;
use Illuminate\Http\JsonResponse;
use App\Support\FormPatterns;
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
        private readonly WorkoutGenerationCooldownService $workoutGenerationCooldownService,
        private readonly WorkoutInsightsService $workoutInsightsService,
        private readonly WorkoutMediaService $workoutMediaService,
        private readonly WorkoutRulesService $workoutRulesService,
        private readonly WorkoutLifecycleService $workoutLifecycleService,
    ) {}

    public function index(Request $request): View
    {
        [$tenant, $trainee] = $this->resolveContext($request);
        $search = trim((string) $request->query('q', ''));

        return view('web.v1.trainee.students.index', [
            'students' => $this->repository->paginateForTrainee($tenant, $trainee->id, $search),
            'search' => $search,
            'metrics' => $this->repository->metricsForTrainee($tenant, $trainee->id),
            'tenant' => $tenant,
        ]);
    }

    public function create(Request $request): View
    {
        [, $trainee] = $this->resolveContext($request);

        return view('web.v1.trainee.students.create', [
            'trainee' => $trainee,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$tenant, $trainee] = $this->resolveContext($request);

        $payload = $request->validate([
            'name' => FormPatterns::name(),
            'email' => FormPatterns::email(),
            'password' => ['required', 'string', 'min:8'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'height' => ['nullable', 'numeric', 'min:0.5', 'max:3'],
            'weight' => ['nullable', 'numeric', 'min:20', 'max:500'],
            'activity_level' => ['nullable', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:500'],
        ]);

        $student = $this->repository->createForTrainee($tenant, $trainee->id, $payload);

        $imc = $this->calculateImc($student->height, $student->weight);
        if ($imc !== null) {
            PhysicalData::query()->updateOrCreate(
                ['user_id' => $student->id],
                [
                    'activity_level' => $payload['activity_level'] ?? 'moderado',
                    'imc' => $imc,
                ]
            );
        }

        return redirect()->route('trainee.students.index')
            ->with('status', 'Aluno criado e vinculado ao trainee com sucesso.');
    }

    public function show(Request $request, int $id): View
    {
        [$tenant, $trainee] = $this->resolveContext($request);
        $student = $this->repository->findForTrainee($tenant, $trainee->id, $id);
        $student->loadMissing(['physicalData', 'medicalData', 'preference']);

        if ($tenant instanceof Tenant) {
            $this->workoutLifecycleService->expireExpiredWorkouts($tenant->id, $student->id);
        }

        $workouts = $tenant instanceof Tenant
            ? Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'status', 'request_status', 'created_at', 'workout_plan'])
            : collect();
        $latestWorkout = $workouts->first();

        return view('web.v1.trainee.students.show', [
            'student' => $student,
            'tenant' => $tenant,
            'workouts' => $workouts,
            'canManageWorkouts' => $tenant instanceof Tenant,
            'latestWorkout' => $latestWorkout,
            'latestWorkoutInsights' => $latestWorkout instanceof Workout
                ? $this->workoutInsightsService->summarize(is_array($latestWorkout->workout_plan) ? $latestWorkout->workout_plan : [])
                : [],
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        [$tenant, $trainee] = $this->resolveContext($request);
        $student = $this->repository->findForTrainee($tenant, $trainee->id, $id);
        $student->loadMissing(['physicalData', 'medicalData', 'preference']);

        return view('web.v1.trainee.students.edit', [
            'student' => $student,
            'tenant' => $tenant,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        [$tenant, $trainee] = $this->resolveContext($request);
        $student = $this->repository->findForTrainee($tenant, $trainee->id, $id);

        $payload = $request->validate([
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'height' => ['nullable', 'numeric', 'min:0.5', 'max:3'],
            'weight' => ['nullable', 'numeric', 'min:20', 'max:500'],
            'body_fat_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'activity_level' => ['nullable', 'string', 'max:255'],
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

        $student->fill([
            'birth_date' => $payload['birth_date'] ?? null,
            'height' => $payload['height'] ?? null,
            'weight' => $payload['weight'] ?? null,
        ]);
        $student->save();

        $imc = $this->calculateImc($student->height, $student->weight);

        PhysicalData::query()->updateOrCreate(
            ['user_id' => $student->id],
            [
                'body_fat_percentage' => $payload['body_fat_percentage'] ?? null,
                'activity_level' => $payload['activity_level'] ?? null,
                'imc' => $imc,
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

        return redirect()->route('trainee.students.show', $student->id)
            ->with('status', 'Dados fisicos, medicos e preferencias atualizados.');
    }

    public function generateWorkout(Request $request, int $id): RedirectResponse
    {
        [$tenant, $trainee] = $this->resolveContext($request);
        $tenant = $this->requireWorkoutTenant($tenant);

        if ($trainee === null) {
            return redirect()->route('login')
                ->withErrors(['workout' => 'Sessao invalida. Faca login novamente.']);
        }

        $student = $this->repository->findForTrainee($tenant, $trainee->id, $id);

        $payload = $request->validate([
            'adjustment_request' => ['nullable', 'string', 'max:1500'],
        ]);

        $adjustmentRequest = trim((string) ($payload['adjustment_request'] ?? ''));
        $normalizedAdjustmentRequest = $adjustmentRequest !== '' ? $adjustmentRequest : null;

        $hasProcessingWorkout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->where('status', 'processing')
            ->exists();

        if ($hasProcessingWorkout) {
            return redirect()->route('trainee.students.show', $student->id)
                ->withErrors(['workout' => 'Ja existe uma geracao em processamento para este aluno. Aguarde finalizar para evitar novo consumo de credito.']);
        }

        try {
            $this->workoutGenerationCooldownService->assertGenerationAllowed($tenant, (int) $student->id, 'este aluno');
        } catch (RuntimeException $exception) {
            return redirect()->route('trainee.students.show', $student->id)
                ->withErrors(['workout' => $exception->getMessage()]);
        }

        try {
            $creditTransaction = $this->creditService->consumeCredits(
                $trainee,
                $this->workoutRulesService->generationCredits(),
                'consume_generation',
                [
                    'context' => 'web_trainee',
                    'tenant_id' => $tenant->id,
                    'trainee_id' => (int) $trainee->id,
                    'student_id' => $student->id,
                ],
                $tenant,
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('trainee.students.show', $student->id)
                ->withErrors(['workout' => $exception->getMessage()]);
        }

        $workout = Workout::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'processing',
            'workout_plan' => ['weekly_plan' => []],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => $this->workoutGenerationCooldownService->withCreditChargeMetadata([], $creditTransaction),
        ], $this->workoutLifecycleService->activeAttributes()));

        GenerateWorkoutJob::dispatch($workout->id, $student->id, $tenant->id, $normalizedAdjustmentRequest, (int) $trainee->id);

        return redirect()->route('trainee.students.show', $student->id)
            ->with('status', 'Geracao de treino com ilustracoes e recomendacoes iniciada. Saldo atual: ' . (int) $trainee->fresh()?->credits_balance . ' credito(s).');
    }

    public function showWorkout(Request $request, int $id, int $workoutId): View
    {
        [$tenant, $trainee] = $this->resolveContext($request);
        $tenant = $this->requireWorkoutTenant($tenant);
        $student = $this->repository->findForTrainee($tenant, $trainee->id, $id);

        $workout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->where('id', $workoutId)
            ->firstOrFail();

        return view('web.v1.trainee.students.workouts.show', [
            'student' => $student,
            'workout' => $this->hydrateWorkoutMedia($workout),
        ]);
    }

    public function searchWorkoutCatalog(Request $request, int $id): JsonResponse
    {
        [$tenant, $trainee] = $this->resolveContext($request);
        $tenant = $this->requireWorkoutTenant($tenant);
        $this->repository->findForTrainee($tenant, $trainee->id, $id);

        $result = $this->exerciseCatalogService->listForInternalApi(
            focus: $request->query('focus'),
            search: $request->query('search'),
            translationStatus: null,
            limit: (int) $request->query('limit', 10),
            offset: 0,
        );

        return response()->json($result);
    }

    public function regenerateWorkout(Request $request, int $id, int $workoutId): RedirectResponse
    {
        [$tenant, $trainee] = $this->resolveContext($request);
        $tenant = $this->requireWorkoutTenant($tenant);

        if ($trainee === null) {
            return redirect()->route('login')
                ->withErrors(['workout' => 'Sessao invalida. Faca login novamente.']);
        }

        $student = $this->repository->findForTrainee($tenant, $trainee->id, $id);

        $payload = $request->validate([
            'adjustment_request' => ['nullable', 'string', 'max:1500'],
        ]);

        $adjustmentRequest = trim((string) ($payload['adjustment_request'] ?? ''));
        $normalizedAdjustmentRequest = $adjustmentRequest !== '' ? $adjustmentRequest : null;

        $hasProcessingWorkout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->where('status', 'processing')
            ->exists();

        if ($hasProcessingWorkout) {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $workoutId])
                ->withErrors(['workout' => 'Ja existe uma geracao em processamento para este aluno. Aguarde finalizar para evitar novo consumo de credito.']);
        }

        $targetWorkout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->where('id', $workoutId)
            ->firstOrFail();

        $targetWorkout = $this->workoutLifecycleService->syncWorkoutStatus($targetWorkout);

        if ((string) ($targetWorkout->request_status ?? 'active') !== 'active') {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $targetWorkout->id])
                ->withErrors(['workout' => 'Treino inativo. Nao e permitido refazer este plano.']);
        }

        if ((string) $targetWorkout->status !== 'done') {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $targetWorkout->id])
                ->withErrors(['workout' => 'Aguarde a conclusao do treino antes de refazer.']);
        }

        try {
            $this->workoutGenerationCooldownService->assertGenerationAllowed($tenant, (int) $student->id, 'este aluno');
        } catch (RuntimeException $exception) {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $targetWorkout->id])
                ->withErrors(['workout' => $exception->getMessage()]);
        }

        try {
            $creditTransaction = $this->creditService->consumeCredits(
                $trainee,
                $this->workoutRulesService->reuseCredits(),
                'consume_regeneration',
                [
                    'context' => 'web_trainee',
                    'tenant_id' => $tenant->id,
                    'trainee_id' => (int) $trainee->id,
                    'student_id' => $student->id,
                    'source_workout_id' => $targetWorkout->id,
                ],
                $tenant,
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $targetWorkout->id])
                ->withErrors(['workout' => $exception->getMessage()]);
        }

        $targetWorkout->fill([
            'request_status' => 'inactive',
        ]);
        $targetWorkout->save();

        $newWorkout = Workout::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'processing',
            'regeneration_request' => $normalizedAdjustmentRequest,
            'workout_plan' => ['weekly_plan' => []],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => $this->workoutGenerationCooldownService->withCreditChargeMetadata([], $creditTransaction),
        ], $this->workoutLifecycleService->activeAttributes()));

        GenerateWorkoutJob::dispatch($newWorkout->id, $student->id, $tenant->id, $normalizedAdjustmentRequest, (int) $trainee->id);

        return redirect()->route('trainee.students.workouts.show', [$student->id, $newWorkout->id])
            ->with('status', 'Refazer treino iniciado com as instrucoes enviadas para a IA. Saldo atual: ' . (int) $trainee->fresh()?->credits_balance . ' credito(s).');
    }

    public function retryWorkout(Request $request, int $id, int $workoutId): RedirectResponse
    {
        [$tenant, $trainee] = $this->resolveContext($request);
        $tenant = $this->requireWorkoutTenant($tenant);

        if ($trainee === null) {
            return redirect()->route('login')
                ->withErrors(['workout' => 'Sessao invalida. Faca login novamente.']);
        }

        $student = $this->repository->findForTrainee($tenant, $trainee->id, $id);

        $targetWorkout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->where('id', $workoutId)
            ->firstOrFail();

        if ((string) $targetWorkout->status !== 'error') {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $targetWorkout->id])
                ->withErrors(['workout' => 'O reenvio esta disponivel apenas para treinos com falha.']);
        }

        $hasProcessingWorkout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->where('status', 'processing')
            ->where('id', '!=', $targetWorkout->id)
            ->exists();

        if ($hasProcessingWorkout) {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $targetWorkout->id])
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
            $tenant->id,
            $targetWorkout->regeneration_request ? (string) $targetWorkout->regeneration_request : null,
            (int) $trainee->id,
        );

        return redirect()->route('trainee.students.workouts.show', [$student->id, $targetWorkout->id])
            ->with('status', 'Transacao reenviada para processamento. Nao houve novo consumo de credito.');
    }

    public function activateWorkout(Request $request, int $id, int $workoutId): RedirectResponse
    {
        [$tenant, $trainee] = $this->resolveContext($request);
        $tenant = $this->requireWorkoutTenant($tenant);
        $student = $this->repository->findForTrainee($tenant, $trainee->id, $id);

        $workout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->where('id', $workoutId)
            ->firstOrFail();

        $workout = $this->workoutLifecycleService->syncWorkoutStatus($workout);

        if ((string) $workout->request_status === 'active') {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $workout->id])
                ->with('status', 'Treino ja esta ativo.');
        }

        try {
            $this->creditService->consumeCredits(
                $trainee,
                $this->workoutRulesService->reactivationCredits(),
                'consume_reactivation',
                [
                    'context' => 'web_trainee',
                    'tenant_id' => $tenant->id,
                    'trainee_id' => (int) $trainee->id,
                    'student_id' => $student->id,
                    'workout_id' => $workout->id,
                ],
                $tenant,
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $workout->id])
                ->withErrors(['workout' => $exception->getMessage()]);
        }

        $this->workoutLifecycleService->activateWorkout(
            Workout::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $student->id),
            $workout,
        );

        return redirect()->route('trainee.students.workouts.show', [$student->id, $workout->id])
            ->with('status', 'Treino ativado com sucesso. Saldo atual: ' . (int) $trainee->fresh()?->credits_balance . ' credito(s).');
    }

    public function inactivateWorkout(Request $request, int $id, int $workoutId): RedirectResponse
    {
        [$tenant, $trainee] = $this->resolveContext($request);
        $tenant = $this->requireWorkoutTenant($tenant);
        $student = $this->repository->findForTrainee($tenant, $trainee->id, $id);

        $workout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->where('id', $workoutId)
            ->firstOrFail();

        $this->workoutLifecycleService->inactivateWorkout($workout);

        return redirect()->route('trainee.students.workouts.show', [$student->id, $workout->id])
            ->with('status', 'Treino inativado com sucesso.');
    }

    public function reuseWorkout(Request $request, int $id, int $workoutId): RedirectResponse
    {
        [$tenant, $trainee] = $this->resolveContext($request);
        $tenant = $this->requireWorkoutTenant($tenant);
        $student = $this->repository->findForTrainee($tenant, $trainee->id, $id);

        $sourceWorkout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->where('id', $workoutId)
            ->firstOrFail();

        if ((string) $sourceWorkout->status !== 'done') {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $sourceWorkout->id])
                ->withErrors(['workout' => 'Somente treino concluido pode ser reaproveitado.']);
        }

        try {
            $this->creditService->consumeCredits(
                $trainee,
                $this->workoutRulesService->reuseCredits(),
                'consume_regeneration',
                [
                    'context' => 'web_trainee',
                    'tenant_id' => $tenant->id,
                    'trainee_id' => (int) $trainee->id,
                    'student_id' => $student->id,
                    'source_workout_id' => $sourceWorkout->id,
                    'mode' => 'manual_reuse',
                ],
                $tenant,
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $sourceWorkout->id])
                ->withErrors(['workout' => $exception->getMessage()]);
        }

        Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->where('request_status', 'active')
            ->update(['request_status' => 'inactive']);

        $newWorkout = Workout::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'user_id' => $student->id,
            'status' => 'done',
            'regeneration_request' => 'Treino reaproveitado manualmente sem chamada de IA.',
            'workout_plan' => $sourceWorkout->workout_plan ?? ['weekly_plan' => []],
            'meal_plan' => $sourceWorkout->meal_plan ?? [],
            'recommendations' => $sourceWorkout->recommendations ?? [],
            'cardio_plan' => $sourceWorkout->cardio_plan ?? [],
            'safety_flags' => $sourceWorkout->safety_flags ?? [],
        ], $this->workoutLifecycleService->activeAttributes()));

        return redirect()->route('trainee.students.workouts.show', [$student->id, $newWorkout->id])
            ->with('status', 'Treino reaproveitado. Agora voce pode editar e reorganizar os exercicios no board.');
    }

    public function updateWorkoutBoard(Request $request, int $id, int $workoutId): RedirectResponse
    {
        [$tenant, $trainee] = $this->resolveContext($request);
        $tenant = $this->requireWorkoutTenant($tenant);
        $student = $this->repository->findForTrainee($tenant, $trainee->id, $id);

        $workout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->where('id', $workoutId)
            ->firstOrFail();

        $workout = $this->workoutLifecycleService->syncWorkoutStatus($workout);

        if ((string) ($workout->request_status ?? 'active') !== 'active') {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $workout->id])
                ->withErrors(['workout' => 'Treino inativo. Nao e permitido editar este plano.']);
        }

        $payload = $request->validate([
            'weekly_plan' => ['required', 'string'],
        ]);

        $decodedPlan = json_decode((string) $payload['weekly_plan'], true);

        if (! is_array($decodedPlan) || $decodedPlan === []) {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $workout->id])
                ->withErrors(['workout' => 'Plano semanal invalido para salvar.']);
        }

        $normalizedPlan = $this->normalizeManualWeeklyPlan($decodedPlan);

        if ($normalizedPlan === []) {
            return redirect()->route('trainee.students.workouts.show', [$student->id, $workout->id])
                ->withErrors(['workout' => 'Nenhum dia valido encontrado no plano para salvar.']);
        }

        $workout->fill([
            'status' => 'done',
            'workout_plan' => $this->workoutMediaService->enrichWorkoutPlan(['weekly_plan' => $normalizedPlan]),
        ]);
        $workout->save();

        return redirect()->route('trainee.students.workouts.show', [$student->id, $workout->id])
            ->with('status', 'Treino atualizado com sucesso pelo board manual.');
    }

    /**
     * @return array{0: Tenant|null, 1: User}
     */
    private function resolveContext(Request $request): array
    {
        $tenant = $request->attributes->get('tenant');
        $trainee = $request->user();

        abort_unless($trainee instanceof User && $trainee->isTrainee(), 403, 'Acesso permitido apenas para trainee.');

        if (! $tenant instanceof Tenant) {
            return [null, $trainee];
        }

        $isLinked = $trainee->traineeTenants()->where('tenants.id', $tenant->id)->exists();

        abort_unless($isLinked, 403, 'Trainee sem vinculo com o tenant selecionado.');

        return [$tenant, $trainee];
    }

    private function requireWorkoutTenant(?Tenant $tenant): Tenant
    {
        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        return redirect()->route('tenants.select')
            ->withErrors(['tenant' => 'Selecione um tenant para gerar e gerenciar treinos dos alunos.'])
            ->throwResponse();
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

    private function calculateImc(mixed $height, mixed $weight): ?float
    {
        if (! is_numeric($height) || ! is_numeric($weight)) {
            return null;
        }

        $normalizedHeight = (float) $height;
        $normalizedWeight = (float) $weight;

        if ($normalizedHeight <= 0 || $normalizedWeight <= 0) {
            return null;
        }

        return round($normalizedWeight / ($normalizedHeight * $normalizedHeight), 2);
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
