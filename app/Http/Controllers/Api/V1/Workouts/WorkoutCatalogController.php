<?php

namespace App\Http\Controllers\Api\V1\Workouts;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Models\Workout\Workout;
use App\Models\Workout\WorkoutCatalog;
use App\Services\Workouts\WorkoutCatalogLinkService;
use App\Services\Workouts\WorkoutLifecycleService;
use App\Services\Workouts\WorkoutMediaService;
use App\Transformers\Workout\WorkoutCatalogTransformer;
use App\Transformers\Workout\StudentWorkoutTransformer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WorkoutCatalogController extends Controller
{
    public function __construct(
        private readonly WorkoutCatalogLinkService $workoutCatalogLinkService,
        private readonly WorkoutMediaService $workoutMediaService,
        private readonly WorkoutLifecycleService $workoutLifecycleService,
        private readonly StudentWorkoutTransformer $studentWorkoutTransformer,
        private readonly WorkoutCatalogTransformer $workoutCatalogTransformer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 15);

        $catalogs = WorkoutCatalog::query()
            ->select('workouts_catalogs.*')
            ->selectRaw(
                'exists(select 1 from workout_catalog_user_links wcul where wcul.workouts_catalog_id = workouts_catalogs.id and wcul.user_id = ?) as is_linked',
                [(int) $user->id],
            )
            ->with('owner:id,name')
            ->with([
                'exercises' => function ($query): void {
                    $query->select([
                        'exercise_media_caches.id',
                        'exercise_media_caches.localized_name_pt_br',
                        'exercise_media_caches.query_name',
                        'exercise_media_caches.workoutx_name',
                        'exercise_media_caches.storage_path',
                        'exercise_media_caches.remote_gif_url',
                    ]);
                },
            ])
            ->where('workouts_catalogs.status', true)
            ->where('workouts_catalogs.is_public', true)
            ->whereNotExists(function ($linkQuery) use ($user): void {
                $linkQuery->selectRaw('1')
                    ->from('workout_catalog_user_links as wcul')
                    ->whereColumn('wcul.workouts_catalog_id', 'workouts_catalogs.id')
                    ->where('wcul.user_id', (int) $user->id);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('workouts_catalogs.name', 'like', '%' . $search . '%')
                        ->orWhere('workouts_catalogs.description', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('workouts_catalogs.id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'per_page' => $perPage,
            ],
            'data' => collect($catalogs->items())
                ->map(function (WorkoutCatalog $catalog): array {
                    $payload = $this->workoutCatalogTransformer->transform(
                        $catalog,
                        (bool) ($catalog->is_linked ?? false),
                    );

                    $payload['exercises_preview'] = $this->buildExercisesPreview($catalog);

                    return $payload;
                })
                ->values(),
            'meta' => $this->paginationMeta($catalogs),
            'links' => $this->paginationLinks($catalogs),
        ]);
    }

    private function resolveExerciseName(mixed $exercise): string
    {
        $name = trim((string) ($exercise->localized_name_pt_br ?: $exercise->query_name ?: $exercise->workoutx_name));

        return $name !== '' ? $name : 'Exercicio sem nome';
    }

    private function resolveExerciseGifUrl(mixed $exercise): ?string
    {
        $workoutxName = trim((string) ($exercise->workoutx_name ?? ''));

        if ($workoutxName !== '') {
            return api_route('api.workouts.exercises.media.show', ['workoutxName' => $workoutxName]);
        }

        $storagePath = trim((string) ($exercise->storage_path ?? ''));
        if ($storagePath !== '') {
            if (str_starts_with($storagePath, 'http://') || str_starts_with($storagePath, 'https://')) {
                return $storagePath;
            }

            return asset('storage/' . ltrim($storagePath, '/'));
        }

        $remoteGifUrl = trim((string) ($exercise->remote_gif_url ?? ''));

        return $remoteGifUrl !== '' ? $remoteGifUrl : null;
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $tenant = $request->attributes->get('tenant');
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = 1;

        $catalogs = WorkoutCatalog::query()
            ->select('workouts_catalogs.*')
            ->with('owner:id,name')
            ->with([
                'exercises' => function ($query): void {
                    $query->select([
                        'exercise_media_caches.id',
                        'exercise_media_caches.localized_name_pt_br',
                        'exercise_media_caches.query_name',
                        'exercise_media_caches.workoutx_name',
                        'exercise_media_caches.storage_path',
                        'exercise_media_caches.remote_gif_url',
                    ]);
                },
                'workouts' => function ($query) use ($user, $tenant): void {
                    $query->select([
                        'id',
                        'tenant_id',
                        'user_id',
                        'source_workout_catalog_id',
                        'source_workout_catalog_name',
                        'status',
                        'request_status',
                        'created_at',
                        'updated_at',
                    ])
                        ->where('user_id', (int) $user->id)
                        ->when(
                            $tenant instanceof Tenant,
                            fn($innerQuery) => $innerQuery->where('tenant_id', $tenant->id),
                            fn($innerQuery) => $innerQuery->whereNull('tenant_id'),
                        )
                        ->orderByDesc('id');
                },
            ])
            ->where('workouts_catalogs.status', true)
            ->whereExists(function ($linkQuery) use ($user): void {
                $linkQuery->selectRaw('1')
                    ->from('workout_catalog_user_links as wcul')
                    ->whereColumn('wcul.workouts_catalog_id', 'workouts_catalogs.id')
                    ->where('wcul.user_id', (int) $user->id);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('workouts_catalogs.name', 'like', '%' . $search . '%')
                        ->orWhere('workouts_catalogs.description', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('workouts_catalogs.id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'per_page' => $perPage,
            ],
            'data' => collect($catalogs->items())
                ->map(function (WorkoutCatalog $catalog): array {
                    $payload = $this->workoutCatalogTransformer->transform($catalog, true);
                    $payload['exercises_preview'] = $this->buildExercisesPreview($catalog);

                    $payload['workouts'] = $catalog->workouts
                        ->map(fn(Workout $workout): array => [
                            'id' => (int) $workout->id,
                            'tenant_id' => $workout->tenant_id === null ? null : (int) $workout->tenant_id,
                            'user_id' => (int) $workout->user_id,
                            'source_workout_catalog_id' => $workout->source_workout_catalog_id === null ? null : (int) $workout->source_workout_catalog_id,
                            'source_workout_catalog_name' => $workout->source_workout_catalog_name,
                            'status' => (string) $workout->status,
                            'request_status' => (string) ($workout->request_status ?? 'active'),
                            'created_at' => optional($workout->created_at)->toISOString(),
                            'updated_at' => optional($workout->updated_at)->toISOString(),
                        ])
                        ->values()
                        ->all();

                    return $payload;
                })
                ->values(),
            'meta' => $this->paginationMeta($catalogs),
            'links' => $this->paginationLinks($catalogs),
        ]);
    }

    public function link(Request $request, int $catalogId): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $catalog = WorkoutCatalog::query()
            ->whereKey($catalogId)
            ->where('status', true)
            ->with([
                'exercises' => function ($query): void {
                    $query->select([
                        'exercise_media_caches.id',
                        'exercise_media_caches.localized_name_pt_br',
                        'exercise_media_caches.query_name',
                        'exercise_media_caches.workoutx_name',
                        'exercise_media_caches.storage_path',
                        'exercise_media_caches.remote_gif_url',
                    ]);
                },
            ])
            ->where(function (Builder $query) use ($user): void {
                $query->where('is_public', true)
                    ->orWhereExists(function ($linkQuery) use ($user): void {
                        $linkQuery->selectRaw('1')
                            ->from('workout_catalog_user_links as wcul')
                            ->whereColumn('wcul.workouts_catalog_id', 'workouts_catalogs.id')
                            ->where('wcul.user_id', (int) $user->id);
                    });
            })
            ->with('owner:id,name')
            ->first();

        if (! $catalog instanceof WorkoutCatalog) {
            return response()->json([
                'message' => 'Catalogo nao encontrado.',
            ], 404);
        }

        $weeklyPlan = $this->buildWeeklyPlanFromCatalog($catalog);

        if ($weeklyPlan === []) {
            return response()->json([
                'message' => 'Catalogo sem exercicios validos para montar um treino.',
            ], 422);
        }

        $tenant = $request->attributes->get('tenant');

        try {
            $linkResult = $this->workoutCatalogLinkService->link(
                $user,
                $catalog,
                $tenant instanceof Tenant ? $tenant : null,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $alreadyLinked = (bool) ($linkResult['already_linked'] ?? false);

        $tenantId = $tenant instanceof Tenant ? (int) $tenant->id : null;

        $workout = Workout::query()->create(array_merge([
            'tenant_id' => $tenantId,
            'user_id' => (int) $user->id,
            'source_workout_catalog_id' => (int) $catalog->id,
            'source_workout_catalog_name' => trim((string) $catalog->name) !== '' ? (string) $catalog->name : null,
            'status' => 'done',
            'regeneration_request' => 'Treino aplicado do catalogo #' . $catalog->id . ' (' . $catalog->name . ').',
            'workout_plan' => $this->workoutMediaService->enrichWorkoutPlan(['weekly_plan' => $weeklyPlan]),
            'meal_plan' => [],
            'recommendations' => [
                'Plano aplicado a partir do catalogo: ' . $catalog->name . '.',
                'Use o editor manual para ajustar series, reps e distribuicao por dia.',
            ],
            'cardio_plan' => [],
            'safety_flags' => [],
        ], $this->workoutLifecycleService->activeAttributes()));

        return response()->json([
            'message' => $alreadyLinked
                ? 'Catalogo ja vinculado para este aluno.'
                : 'Catalogo vinculado com sucesso.',
            'credits_consumed' => (int) ($linkResult['credits_consumed'] ?? 0),
            'credits_balance' => (int) ($user->fresh()?->credits_balance ?? 0),
            'data' => array_merge(
                $this->workoutCatalogTransformer->transform($catalog, true),
                [
                    'exercises_preview' => $this->buildExercisesPreview($catalog),
                ],
            ),
            'workout' => $this->studentWorkoutTransformer->transform($workout),
        ], $alreadyLinked ? 200 : 201);
    }

    private function buildWeeklyPlanFromCatalog(WorkoutCatalog $catalog): array
    {
        $preparedExercises = $catalog->exercises
            ->map(function ($exercise): ?array {
                $payload = is_array($exercise->payload ?? null) ? $exercise->payload : [];
                $name = trim((string) ($exercise->localized_name_pt_br ?: $exercise->query_name ?: $exercise->workoutx_name));

                if ($name === '') {
                    return null;
                }

                $focus = trim((string) ($payload['focus'] ?? ''));

                return [
                    'name' => $name,
                    'category' => $this->inferCatalogExerciseCategory($payload, $focus, $name),
                    'sets' => 3,
                    'reps' => '10-12',
                    'rest' => '60s',
                    'notes' => '',
                    'steps' => [],
                    'remote_exercise_id' => trim((string) ($exercise->remote_exercise_id ?? '')),
                    'workoutx_name' => $this->workoutMediaService->normalizeWorkoutxName($exercise->workoutx_name, $name),
                    'exercise_media_path' => trim((string) ($exercise->storage_path ?? '')),
                    'exercise_media_url' => '',
                    '_focus' => $focus,
                ];
            })
            ->filter()
            ->values();

        if ($preparedExercises->isEmpty()) {
            return [];
        }

        $weeklyPlan = [];

        foreach ($preparedExercises->chunk(5)->values() as $index => $chunk) {
            $dayFocus = '';
            $dayExercises = [];

            foreach ($chunk as $exercise) {
                $focusHint = trim((string) ($exercise['_focus'] ?? ''));
                if ($dayFocus === '' && $focusHint !== '') {
                    $dayFocus = $focusHint;
                }

                unset($exercise['_focus']);
                $dayExercises[] = $exercise;
            }

            $weeklyPlan[] = [
                'day' => 'Dia ' . ($index + 1),
                'focus' => $dayFocus !== '' ? $dayFocus : 'Treino geral',
                'exercises' => $dayExercises,
            ];
        }

        return $weeklyPlan;
    }

    private function inferCatalogExerciseCategory(array $payload, string $focus, string $name): string
    {
        $category = mb_strtolower(trim((string) ($payload['category'] ?? '')));

        if (in_array($category, ['specific', 'cardio'], true)) {
            return $category;
        }

        $haystacks = [
            mb_strtolower($focus),
            mb_strtolower((string) ($payload['target'] ?? '')),
            mb_strtolower($name),
        ];

        foreach ($haystacks as $haystack) {
            if (str_contains($haystack, 'cardio') || str_contains($haystack, 'aerob')) {
                return 'cardio';
            }
        }

        return 'specific';
    }

    private function buildExercisesPreview(WorkoutCatalog $catalog): array
    {
        return $catalog->exercises
            ->take(4)
            ->map(fn($exercise): array => [
                'id' => (int) $exercise->id,
                'name' => $this->resolveExerciseName($exercise),
                'gif_url' => $this->resolveExerciseGifUrl($exercise),
            ])
            ->values()
            ->all();
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    private function paginationLinks(LengthAwarePaginator $paginator): array
    {
        return [
            'first' => $paginator->url(1),
            'last' => $paginator->url(max(1, $paginator->lastPage())),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }
}
