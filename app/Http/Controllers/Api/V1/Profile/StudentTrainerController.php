<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\Contracts\Tenant\TraineeStudentRepositoryContract;
use App\Transformers\Profile\StudentTrainerTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentTrainerController extends Controller
{
    public function __construct(
        private readonly TraineeStudentRepositoryContract $traineeStudentRepository,
        private readonly StudentTrainerTransformer $studentTrainerTransformer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || $user->profileType() !== Role::STUDENT) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $assignedTrainee = $this->traineeStudentRepository->assignedTraineeForStudent(null, (int) $user->id);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 15);
        $trainers = $this->traineeStudentRepository->paginateStandaloneTrainees($search, $perPage);

        return response()->json([
            'assigned_trainer_id' => $assignedTrainee?->id,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'per_page' => $perPage,
            ],
            'data' => collect($trainers->items())->map(fn(User $trainer) => $this->studentTrainerTransformer->transform(
                $trainer,
                $assignedTrainee?->id === $trainer->id,
            ))->values(),
            'meta' => [
                'current_page' => $trainers->currentPage(),
                'per_page' => $trainers->perPage(),
                'total' => $trainers->total(),
                'last_page' => $trainers->lastPage(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || $user->profileType() !== Role::STUDENT) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $validated = $request->validate([
            'trainee_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $this->traineeStudentRepository->reassignStudentTrainee(
            null,
            (int) $user->id,
            (int) $validated['trainee_user_id'],
            (int) $user->id,
        );

        $assignedTrainee = $this->traineeStudentRepository->assignedTraineeForStudent(null, (int) $user->id);

        return response()->json([
            'message' => 'Trainer atualizado com sucesso.',
            'assigned_trainer' => $assignedTrainee === null ? null : $this->studentTrainerTransformer->transformAssigned($assignedTrainee),
        ]);
    }
}
