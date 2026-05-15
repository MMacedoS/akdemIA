<?php

namespace App\Http\Controllers\Web\V1\Workouts;

use App\Http\Controllers\Controller;
use App\Models\Workout\ExerciseMediaCache;
use App\Models\Workout\WorkoutCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function indexTrainer(Request $request): View
    {
        return $this->index($request, 'trainer');
    }

    public function createTrainer(Request $request): View
    {
        return $this->create($request, 'trainer');
    }

    public function storeTrainer(Request $request): RedirectResponse
    {
        return $this->store($request, 'trainer');
    }

    public function editTrainer(Request $request, WorkoutCatalog $catalog): View
    {
        return $this->edit($request, $catalog, 'trainer');
    }

    public function updateTrainer(Request $request, WorkoutCatalog $catalog): RedirectResponse
    {
        return $this->update($request, $catalog, 'trainer');
    }

    public function destroyTrainer(Request $request, WorkoutCatalog $catalog): RedirectResponse
    {
        return $this->destroy($request, $catalog, 'trainer');
    }

    public function optionsTrainer(Request $request): JsonResponse
    {
        return $this->options($request, 'trainer');
    }

    public function indexAdmin(Request $request): View
    {
        return $this->index($request, 'admin');
    }

    public function createAdmin(Request $request): View
    {
        return $this->create($request, 'admin');
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        return $this->store($request, 'admin');
    }

    public function editAdmin(Request $request, WorkoutCatalog $catalog): View
    {
        return $this->edit($request, $catalog, 'admin');
    }

    public function updateAdmin(Request $request, WorkoutCatalog $catalog): RedirectResponse
    {
        return $this->update($request, $catalog, 'admin');
    }

    public function destroyAdmin(Request $request, WorkoutCatalog $catalog): RedirectResponse
    {
        return $this->destroy($request, $catalog, 'admin');
    }

    public function optionsAdmin(Request $request): JsonResponse
    {
        return $this->options($request, 'admin');
    }

    private function index(Request $request, string $panel): View
    {
        $search = trim((string) $request->query('q', ''));
        $user = $request->user();

        $catalogs = WorkoutCatalog::query()
            ->withCount('exercises')
            ->with('owner:id,name')
            ->when($panel === 'trainer', function (Builder $query) use ($user): void {
                $query->where(function (Builder $innerQuery) use ($user): void {
                    $innerQuery->where('user_id', $user?->id)
                        ->orWhere('is_public', true);
                });
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $innerQuery) use ($search): void {
                    $innerQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('web.v1.workouts.catalog.index', [
            'catalogs' => $catalogs,
            'search' => $search,
            'panel' => $panel,
            'routePrefix' => $this->routePrefix($panel),
        ]);
    }

    private function create(Request $request, string $panel): View
    {
        $exerciseSearch = trim((string) $request->query('exercise_search', ''));
        $exerciseFocus = trim((string) $request->query('exercise_focus', ''));
        $exerciseTarget = trim((string) $request->query('exercise_target', ''));
        $selectedExerciseIds = [];
        $exerciseOptions = $this->loadExerciseOptions($exerciseSearch, $selectedExerciseIds, $exerciseFocus, $exerciseTarget);
        $availableFilterOptions = $this->loadAvailableFilterOptions();

        return view('web.v1.workouts.catalog.form', [
            'panel' => $panel,
            'routePrefix' => $this->routePrefix($panel),
            'exerciseOptionsEndpoint' => route($this->routePrefix($panel) . '.options'),
            'catalog' => new WorkoutCatalog(),
            'exerciseOptions' => $exerciseOptions,
            'selectedExerciseIds' => $selectedExerciseIds,
            'exerciseSearch' => $exerciseSearch,
            'exerciseFocus' => $exerciseFocus,
            'exerciseTarget' => $exerciseTarget,
            'exerciseFocusOptions' => $availableFilterOptions['focus'],
            'exerciseTargetOptions' => $availableFilterOptions['target'],
            'submitLabel' => 'Salvar catalogo',
        ]);
    }

    private function store(Request $request, string $panel): RedirectResponse
    {
        $payload = $this->validatedPayload($request);
        $user = $request->user();

        $catalog = DB::transaction(function () use ($payload, $user): WorkoutCatalog {
            $exerciseIds = $this->resolveOrderedExerciseIds($payload);

            $catalog = WorkoutCatalog::query()->create([
                'name' => $payload['name'],
                'description' => $payload['description'],
                'quantity_exercises' => count($exerciseIds),
                'price' => 0,
                'user_id' => $user?->id,
                'path_image' => $payload['path_image'] ?? null,
                'is_public' => (bool) ($payload['is_public'] ?? false),
                'status' => (bool) ($payload['status'] ?? true),
            ]);

            $catalog->exercises()->sync($this->exerciseSyncMap($exerciseIds));

            return $catalog;
        });

        return redirect()->route($this->routePrefix($panel) . '.edit', $catalog->id)
            ->with('status', 'Catalogo salvo com sucesso. Treino pronto para uso interno.');
    }

    private function edit(Request $request, WorkoutCatalog $catalog, string $panel): View
    {
        $this->assertCanManage($request, $catalog, $panel);

        $exerciseSearch = trim((string) $request->query('exercise_search', ''));
        $exerciseFocus = trim((string) $request->query('exercise_focus', ''));
        $exerciseTarget = trim((string) $request->query('exercise_target', ''));
        $selectedExerciseIds = $catalog->exercises()
            ->pluck('exercise_media_caches.id')
            ->map(static fn(mixed $id): int => (int) $id)
            ->all();
        $exerciseOptions = $this->loadExerciseOptions($exerciseSearch, $selectedExerciseIds, $exerciseFocus, $exerciseTarget);
        $availableFilterOptions = $this->loadAvailableFilterOptions();

        return view('web.v1.workouts.catalog.form', [
            'panel' => $panel,
            'routePrefix' => $this->routePrefix($panel),
            'exerciseOptionsEndpoint' => route($this->routePrefix($panel) . '.options'),
            'catalog' => $catalog,
            'exerciseOptions' => $exerciseOptions,
            'selectedExerciseIds' => $selectedExerciseIds,
            'exerciseSearch' => $exerciseSearch,
            'exerciseFocus' => $exerciseFocus,
            'exerciseTarget' => $exerciseTarget,
            'exerciseFocusOptions' => $availableFilterOptions['focus'],
            'exerciseTargetOptions' => $availableFilterOptions['target'],
            'submitLabel' => 'Atualizar catalogo',
        ]);
    }

    private function update(Request $request, WorkoutCatalog $catalog, string $panel): RedirectResponse
    {
        $this->assertCanManage($request, $catalog, $panel);

        $payload = $this->validatedPayload($request, $catalog->id);

        DB::transaction(function () use ($catalog, $payload): void {
            $exerciseIds = $this->resolveOrderedExerciseIds($payload);

            $catalog->fill([
                'name' => $payload['name'],
                'description' => $payload['description'],
                'quantity_exercises' => count($exerciseIds),
                'path_image' => $payload['path_image'] ?? null,
                'is_public' => (bool) ($payload['is_public'] ?? false),
                'status' => (bool) ($payload['status'] ?? true),
            ]);
            $catalog->save();

            $catalog->exercises()->sync($this->exerciseSyncMap($exerciseIds));
        });

        return redirect()->route($this->routePrefix($panel) . '.edit', $catalog->id)
            ->with('status', 'Catalogo atualizado com sucesso.');
    }

    private function destroy(Request $request, WorkoutCatalog $catalog, string $panel): RedirectResponse
    {
        $this->assertCanManage($request, $catalog, $panel);

        $catalog->delete();

        return redirect()->route($this->routePrefix($panel))
            ->with('status', 'Catalogo removido com sucesso.');
    }

    private function options(Request $request, string $panel): JsonResponse
    {
        $search = trim((string) $request->query('exercise_search', ''));
        $focus = trim((string) $request->query('exercise_focus', ''));
        $target = trim((string) $request->query('exercise_target', ''));
        $selectedIds = collect((array) $request->query('selected_ids', []))
            ->map(static fn(mixed $id): int => (int) $id)
            ->filter(static fn(int $id): bool => $id > 0)
            ->values()
            ->all();

        $exerciseOptions = $this->loadExerciseOptions($search, $selectedIds, $focus, $target);
        $availableFilterOptions = $this->loadAvailableFilterOptions();

        return response()->json([
            'panel' => $panel,
            'exerciseOptions' => $exerciseOptions,
            'exerciseFocusOptions' => $availableFilterOptions['focus'],
            'exerciseTargetOptions' => $availableFilterOptions['target'],
        ]);
    }

    private function validatedPayload(Request $request, ?int $catalogId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('workouts_catalogs', 'name')->ignore($catalogId)],
            'description' => ['required', 'string', 'max:4000'],
            'path_image' => ['nullable', 'string', 'max:100'],
            'is_public' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
            'exercise_media_cache_ids' => ['required', 'array', 'min:1'],
            'exercise_media_cache_ids.*' => ['integer', Rule::exists('exercise_media_caches', 'id')],
            'exercise_order_ids' => ['nullable', 'array'],
            'exercise_order_ids.*' => ['integer', Rule::exists('exercise_media_caches', 'id')],
        ]);
    }

    private function resolveOrderedExerciseIds(array $payload): array
    {
        $selectedIds = array_values(array_unique(array_map('intval', $payload['exercise_media_cache_ids'] ?? [])));
        $orderIds = array_values(array_unique(array_map('intval', $payload['exercise_order_ids'] ?? [])));

        $orderedSelectedIds = array_values(array_intersect($orderIds, $selectedIds));
        $remainingIds = array_values(array_diff($selectedIds, $orderedSelectedIds));

        return [...$orderedSelectedIds, ...$remainingIds];
    }

    private function exerciseSyncMap(array $exerciseIds): array
    {
        $sync = [];

        foreach (array_values($exerciseIds) as $index => $exerciseId) {
            $sync[$exerciseId] = ['order' => $index + 1];
        }

        return $sync;
    }

    private function loadExerciseOptions(string $search, array $selectedIds, string $focusFilter = '', string $targetFilter = ''): Collection
    {
        $baseQuery = ExerciseMediaCache::query()
            ->select(['id', 'localized_name_pt_br', 'query_name', 'workoutx_name', 'storage_path', 'remote_gif_url', 'payload'])
            ->whereNotNull('remote_exercise_id');

        $searched = (clone $baseQuery)
            ->when($focusFilter !== '', function (Builder $query) use ($focusFilter): void {
                $query->where('payload->focus', $focusFilter);
            })
            ->when($targetFilter !== '', function (Builder $query) use ($targetFilter): void {
                $query->where('payload->target', $targetFilter);
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $innerQuery) use ($search): void {
                    $innerQuery->where('localized_name_pt_br', 'like', '%' . $search . '%')
                        ->orWhere('query_name', 'like', '%' . $search . '%')
                        ->orWhere('workoutx_name', 'like', '%' . $search . '%');
                });
            })
            ->limit(80)
            ->get();

        $selected = empty($selectedIds)
            ? collect()
            : (clone $baseQuery)->whereIn('id', $selectedIds)->get();

        return $selected
            ->concat($searched)
            ->unique('id')
            ->map(function (ExerciseMediaCache $exercise): array {
                $payload = is_array($exercise->payload) ? $exercise->payload : [];
                $name = trim((string) ($exercise->localized_name_pt_br ?: $exercise->query_name ?: $exercise->workoutx_name));

                return [
                    'id' => (int) $exercise->id,
                    'name' => $name !== '' ? $name : 'Exercicio sem nome',
                    'focus' => (string) ($payload['focus'] ?? 'geral'),
                    'target' => (string) ($payload['target'] ?? ''),
                    'equipment' => (string) ($payload['equipment'] ?? ''),
                    'body_part' => (string) ($payload['body_part'] ?? ''),
                    'image_url' => $this->resolveExerciseImageUrl($exercise),
                ];
            })
            ->sortBy(static fn(array $row): string => mb_strtolower((string) $row['name']))
            ->values();
    }

    private function resolveExerciseImageUrl(ExerciseMediaCache $exercise): ?string
    {
        $workoutxName = trim((string) $exercise->workoutx_name);

        if ($workoutxName !== '') {
            return api_route('api.workouts.exercises.media.show', ['workoutxName' => $workoutxName]);
        }

        $storagePath = trim((string) $exercise->storage_path);
        if ($storagePath !== '') {
            if (str_starts_with($storagePath, 'http://') || str_starts_with($storagePath, 'https://')) {
                return $storagePath;
            }

            return asset('storage/' . ltrim($storagePath, '/'));
        }

        $remoteGifUrl = trim((string) $exercise->remote_gif_url);

        return $remoteGifUrl !== '' ? $remoteGifUrl : null;
    }

    private function extractFilterOptions(Collection $exerciseOptions, string $key): array
    {
        return $exerciseOptions
            ->pluck($key)
            ->map(static fn(mixed $value): string => trim((string) $value))
            ->filter(static fn(string $value): bool => $value !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function loadAvailableFilterOptions(): array
    {
        $payloads = ExerciseMediaCache::query()
            ->whereNotNull('remote_exercise_id')
            ->pluck('payload');

        $focuses = collect();
        $targets = collect();

        foreach ($payloads as $payload) {
            $data = is_array($payload) ? $payload : [];
            $focus = trim((string) ($data['focus'] ?? ''));
            $target = trim((string) ($data['target'] ?? ''));

            if ($focus !== '') {
                $focuses->push($focus);
            }

            if ($target !== '') {
                $targets->push($target);
            }
        }

        return [
            'focus' => $focuses->unique()->sort()->values()->all(),
            'target' => $targets->unique()->sort()->values()->all(),
        ];
    }

    private function assertCanManage(Request $request, WorkoutCatalog $catalog, string $panel): void
    {
        if ($panel === 'admin') {
            return;
        }

        if ((int) $catalog->user_id !== (int) $request->user()?->id) {
            abort(403, 'Voce nao tem permissao para gerenciar este catalogo.');
        }
    }

    private function routePrefix(string $panel): string
    {
        return $panel . '.workouts.catalogs';
    }
}
