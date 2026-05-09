<?php

namespace App\Services\Workouts;

use App\Models\Workout\ExerciseMediaCache;
use Illuminate\Support\Collection;

/**
 * Service facade para leitura e auditoria do catalogo local de exercicios.
 */
class ExerciseCatalogService
{
    public function buildAiCatalogSnapshot(): array
    {
        $bucketLimit = max(1, (int) config('services.internal_catalog.ai_bucket_limit', 12));

        $grouped = $this->baseCatalogQuery()
            ->get()
            ->map(fn(ExerciseMediaCache $exercise): array => $this->transformCatalogExercise($exercise))
            ->groupBy('focus')
            ->map(function (Collection $items) use ($bucketLimit): array {
                return $items
                    ->sortBy(fn(array $item): string => mb_strtolower((string) ($item['localized_name_pt_br'] ?: $item['name'])))
                    ->take($bucketLimit)
                    ->values()
                    ->map(fn(array $item): array => [
                        'id' => $item['id'],
                        'localized_name_pt_br' => $item['localized_name_pt_br'],
                        'name' => $item['name'],
                        'workoutx_name' => $item['workoutx_name'],
                        'target' => $item['target'],
                        'equipment' => $item['equipment'],
                    ])
                    ->all();
            })
            ->filter(fn(array $items): bool => $items !== [])
            ->sortKeys()
            ->all();

        return [
            'bucket_limit' => $bucketLimit,
            'catalog' => $grouped,
        ];
    }

    public function listForInternalApi(?string $focus = null, ?string $search = null, ?string $translationStatus = null, int $limit = 50, int $offset = 0): array
    {
        $normalizedFocus = $this->normalizeFocusFilter($focus);
        $normalizedSearch = mb_strtolower(trim((string) $search));
        $normalizedTranslationStatus = $this->normalizeTranslationStatus($translationStatus);

        $items = $this->baseCatalogQuery()
            ->get()
            ->map(fn(ExerciseMediaCache $exercise): array => $this->transformCatalogExercise($exercise, true));

        if ($normalizedFocus !== null) {
            $items = $items->filter(fn(array $item): bool => $item['focus'] === $normalizedFocus);
        }

        if ($normalizedSearch !== '') {
            $items = $items->filter(function (array $item) use ($normalizedSearch): bool {
                $haystacks = [
                    $item['localized_name_pt_br'],
                    $item['name'],
                    $item['workoutx_name'],
                    $item['target'],
                    $item['equipment'],
                    $item['body_part'],
                ];

                foreach ($haystacks as $haystack) {
                    if (str_contains(mb_strtolower((string) $haystack), $normalizedSearch)) {
                        return true;
                    }
                }

                return false;
            });
        }

        if ($normalizedTranslationStatus !== null) {
            $items = $items->filter(function (array $item) use ($normalizedTranslationStatus): bool {
                $translated = trim((string) ($item['localized_name_pt_br'] ?? '')) !== '';

                return $normalizedTranslationStatus === 'translated'
                    ? $translated
                    : ! $translated;
            });
        }

        $total = $items->count();
        $safeOffset = max(0, $offset);
        $safeLimit = max(1, min($limit, (int) config('services.internal_catalog.endpoint_limit', 100)));

        return [
            'meta' => [
                'total' => $total,
                'count' => min($safeLimit, max($total - $safeOffset, 0)),
                'limit' => $safeLimit,
                'offset' => $safeOffset,
                'focus' => $normalizedFocus,
                'search' => $normalizedSearch,
                'translation_status' => $normalizedTranslationStatus,
                'available_focuses' => $this->availableFocuses(),
                'translation_summary' => $this->translationSummary(),
            ],
            'data' => $items
                ->values()
                ->slice($safeOffset, $safeLimit)
                ->values()
                ->all(),
        ];
    }

    public function auditCatalog(?string $focus = null, ?string $search = null, ?string $translationStatus = null, int $limit = 25, int $page = 1): array
    {
        $safePage = max(1, $page);
        $safeLimit = max(1, min($limit, 100));
        $offset = ($safePage - 1) * $safeLimit;

        $result = $this->listForInternalApi($focus, $search, $translationStatus, $safeLimit, $offset);
        $total = (int) data_get($result, 'meta.total', 0);

        return [
            'filters' => [
                'focus' => data_get($result, 'meta.focus'),
                'search' => data_get($result, 'meta.search'),
                'translation_status' => data_get($result, 'meta.translation_status'),
                'limit' => $safeLimit,
                'page' => $safePage,
            ],
            'summary' => [
                'total' => $this->baseCatalogQuery()->count(),
                'translated' => (int) data_get($result, 'meta.translation_summary.translated', 0),
                'pending_translation' => (int) data_get($result, 'meta.translation_summary.pending_translation', 0),
            ],
            'available_focuses' => data_get($result, 'meta.available_focuses', []),
            'rows' => $result['data'],
            'pagination' => [
                'total' => $total,
                'page' => $safePage,
                'per_page' => $safeLimit,
                'last_page' => max(1, (int) ceil($total / $safeLimit)),
                'has_previous' => $safePage > 1,
                'has_next' => ($offset + $safeLimit) < $total,
            ],
        ];
    }

    private function baseCatalogQuery()
    {
        return ExerciseMediaCache::query()
            ->whereNotNull('remote_exercise_id')
            ->select([
                'remote_exercise_id',
                'localized_name_pt_br',
                'workoutx_name',
                'query_name',
                'remote_gif_url',
                'storage_path',
                'payload',
            ])
            ->orderBy('remote_exercise_id');
    }

    private function transformCatalogExercise(ExerciseMediaCache $exercise, bool $includeMedia = false): array
    {
        $payload = is_array($exercise->payload) ? $exercise->payload : [];
        $bodyPart = mb_strtolower(trim((string) data_get($payload, 'bodyPart', 'general')));

        if ($bodyPart === '') {
            $bodyPart = 'general';
        }

        $item = [
            'id' => (string) $exercise->remote_exercise_id,
            'localized_name_pt_br' => trim((string) ($exercise->localized_name_pt_br ?? '')),
            'name' => trim((string) (data_get($payload, 'name') ?: $exercise->query_name ?: $exercise->workoutx_name)),
            'workoutx_name' => (string) $exercise->workoutx_name,
            'target' => trim((string) data_get($payload, 'target', '')),
            'equipment' => trim((string) data_get($payload, 'equipment', '')),
            'body_part' => $bodyPart,
            'focus' => $this->resolveFocusBucket($bodyPart),
            'translation_status' => trim((string) ($exercise->localized_name_pt_br ?? '')) !== '' ? 'translated' : 'pending',
        ];

        if (! $includeMedia) {
            return $item;
        }

        $item['remote_gif_url'] = trim((string) ($exercise->remote_gif_url ?? data_get($payload, 'gifUrl', '')));
        $item['storage_path'] = trim((string) ($exercise->storage_path ?? ''));

        return $item;
    }

    private function resolveFocusBucket(string $bodyPart): string
    {
        return match ($bodyPart) {
            'chest' => 'peito',
            'back' => 'costas',
            'shoulders' => 'ombros',
            'upper arms', 'lower arms' => 'bracos',
            'upper legs', 'lower legs' => 'pernas',
            'waist' => 'core',
            'cardio' => 'cardio',
            'neck' => 'mobilidade',
            default => 'geral',
        };
    }

    private function normalizeFocusFilter(?string $focus): ?string
    {
        $normalized = mb_strtolower(trim((string) $focus));

        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'peito', 'chest' => 'peito',
            'costas', 'back' => 'costas',
            'ombro', 'ombros', 'shoulder', 'shoulders' => 'ombros',
            'braço', 'bracos', 'braco', 'arms', 'arm' => 'bracos',
            'perna', 'pernas', 'legs', 'leg' => 'pernas',
            'core', 'abdomen', 'abdomem', 'waist' => 'core',
            'cardio' => 'cardio',
            'mobilidade', 'neck' => 'mobilidade',
            default => 'geral',
        };
    }

    private function normalizeTranslationStatus(?string $translationStatus): ?string
    {
        $normalized = mb_strtolower(trim((string) $translationStatus));

        return match ($normalized) {
            'translated', 'traduzido', 'traduzidos' => 'translated',
            'pending', 'pendente', 'pendentes', 'pending_translation' => 'pending',
            default => null,
        };
    }

    private function availableFocuses(): array
    {
        return $this->baseCatalogQuery()
            ->get()
            ->map(fn(ExerciseMediaCache $exercise): string => $this->resolveFocusBucket(mb_strtolower(trim((string) data_get($exercise->payload, 'bodyPart', 'general')))))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function translationSummary(): array
    {
        $translated = $this->baseCatalogQuery()
            ->whereNotNull('localized_name_pt_br')
            ->where('localized_name_pt_br', '!=', '')
            ->count();

        $total = $this->baseCatalogQuery()->count();

        return [
            'translated' => $translated,
            'pending_translation' => max($total - $translated, 0),
        ];
    }
}
