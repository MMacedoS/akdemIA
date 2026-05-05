<?php

namespace App\Http\Controllers\Web\V1\Students;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateWorkoutJob;
use App\Models\Tenant\Tenant;
use App\Models\Workout\Workout;
use App\Services\Credits\CreditService;
use App\Services\Workouts\WorkoutMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class WorkoutController extends Controller
{
    public function __construct(
        private readonly WorkoutMediaService $workoutMediaService,
        private readonly CreditService $creditService
    ) {}

    public function show(Request $request): View
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $workout = $this->resolveCurrentWorkout($tenant, (int) $user->id);

        return view('web.v1.students.workouts.show', [
            'workout' => $workout,
        ]);
    }

    public function start(Request $request): View
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $workout = $this->resolveCurrentWorkout($tenant, (int) $user->id);

        return view('web.v1.students.workouts.start', [
            'workout' => $workout,
        ]);
    }

    public function activate(Request $request, int $workoutId): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $workout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', (int) $user->id)
            ->where('id', $workoutId)
            ->firstOrFail();

        Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', (int) $user->id)
            ->where('id', '!=', $workout->id)
            ->where('request_status', 'active')
            ->update(['request_status' => 'inactive']);

        $workout->request_status = 'active';
        $workout->save();

        return redirect()->route('students.workout.show')
            ->with('status', 'Treino ativado com sucesso.');
    }

    public function inactivate(Request $request, int $workoutId): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $workout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', (int) $user->id)
            ->where('id', $workoutId)
            ->firstOrFail();

        $workout->request_status = 'inactive';
        $workout->save();

        return redirect()->route('students.workout.show')
            ->with('status', 'Treino inativado com sucesso.');
    }

    private function resolveTenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            abort(409, 'Tenant not identified.');
        }

        return $tenant;
    }

    private function resolveCurrentWorkout(Tenant $tenant, int $userId): ?Workout
    {
        $doneWorkout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->where('status', 'done')
            ->orderByDesc('id')
            ->first();

        if ($doneWorkout instanceof Workout) {
            return $this->hydrateWorkoutMedia($doneWorkout);
        }

        $workout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first();

        return $workout instanceof Workout ? $this->hydrateWorkoutMedia($workout) : null;
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

    public function generate(Request $request): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $hasProcessingWorkout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', (int) $user->id)
            ->where('status', 'processing')
            ->exists();

        if ($hasProcessingWorkout) {
            return redirect()->route('students.workout.show')
                ->withErrors(['workout' => 'Ja existe uma geracao em processamento para voce.']);
        }

        try {
            $this->creditService->consumeCredits(
                $user,
                2,
                'consume_generation',
                [
                    'context' => 'web_student',
                    'tenant_id' => $tenant->id,
                    'student_id' => (int) $user->id,
                ],
                $tenant,
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('students.workout.show')
                ->withErrors(['workout' => $exception->getMessage()]);
        }

        $workout = Workout::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => (int) $user->id,
            'status' => 'processing',
            'request_status' => 'active',
            'workout_plan' => ['weekly_plan' => []],
            'meal_plan' => [],
            'recommendations' => [],
            'cardio_plan' => [],
            'safety_flags' => [],
        ]);

        GenerateWorkoutJob::dispatch($workout->id, (int) $user->id, $tenant->id, null, (int) $user->id);

        return redirect()->route('students.workout.show')
            ->with('status', 'Geracao do treino iniciada. Saldo atual: ' . (int) $user->fresh()?->credits_balance . ' credito(s).');
    }

    public function retryWorkout(Request $request, int $workoutId): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);
        $user = $request->user();

        $targetWorkout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('id', $workoutId)
            ->firstOrFail();

        if ((string) $targetWorkout->status !== 'error') {
            return redirect()->route('trainer.students.workouts.show', [$user->id, $targetWorkout->id])
                ->withErrors(['workout' => 'O reenvio esta disponivel apenas para treinos com falha.']);
        }

        $hasProcessingWorkout = Workout::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'processing')
            ->where('id', '!=', $targetWorkout->id)
            ->exists();

        if ($hasProcessingWorkout) {
            return redirect()->route('trainer.students.workouts.show', [$user->id, $targetWorkout->id])
                ->withErrors(['workout' => 'Ja existe outra geracao em processamento para este aluno.']);
        }

        $targetWorkout->fill([
            'status' => 'processing',
            'safety_flags' => [],
        ]);
        $targetWorkout->save();

        GenerateWorkoutJob::dispatch(
            $targetWorkout->id,
            $user->id,
            $tenant->id,
            $targetWorkout->regeneration_request ? (string) $targetWorkout->regeneration_request : null,
            (int) $user->id,
        );

        return redirect()->route('students.workout.show', [$user->id, $targetWorkout->id])
            ->with('status', 'Transacao reenviada para processamento. Nao houve novo consumo de credito.');
    }
}
