@php
    $exerciseAssetBuilder = app(\App\Support\Workout\ExerciseAssetBuilder::class);
    $normalizeExerciseMediaUrl = static function (array $exercise): string {
        $mediaUrl = trim((string) data_get($exercise, 'exercise_media_url', ''));
        $workoutxName = trim((string) data_get($exercise, 'workoutx_name', ''));

        if ($workoutxName === '') {
            return $mediaUrl;
        }

        if ($mediaUrl === ''
            || $mediaUrl === $workoutxName
            || str_contains($mediaUrl, '/storage/exercises/')
            || (! str_contains($mediaUrl, '://')
                && ! str_starts_with($mediaUrl, '/')
                && preg_match('/^[a-z0-9-]+$/', $mediaUrl) === 1)
        ) {
            return route('api.workouts.exercises.media.show', ['workoutxName' => $workoutxName]);
        }

        return $mediaUrl;
    };
    $editable = (bool) ($editable ?? false);
    $updateRoute = (string) ($updateRoute ?? '');
    $regenerateRoute = (string) ($regenerateRoute ?? '');
    $reuseRoute = (string) ($reuseRoute ?? '');
    $activateRoute = (string) ($activateRoute ?? '');
    $inactivateRoute = (string) ($inactivateRoute ?? '');

    $weeklyPlan = data_get($workout, 'workout_plan.weekly_plan', []);
    if (! is_array($weeklyPlan)) {
        $weeklyPlan = [];
    }

    $weeklyPlan = collect($weeklyPlan)
        ->map(function ($dayPlan) use ($exerciseAssetBuilder, $normalizeExerciseMediaUrl) {
            $exercises = data_get($dayPlan, 'exercises', []);

            if (! is_array($exercises)) {
                $exercises = [];
            }

            data_set($dayPlan, 'exercises', collect($exercises)
                ->map(function ($exercise) use ($exerciseAssetBuilder, $normalizeExerciseMediaUrl) {
                    $name = trim((string) data_get($exercise, 'name', 'Exercicio'));
                    $notes = trim((string) data_get($exercise, 'notes', ''));
                    $steps = $exerciseAssetBuilder->normalizeSteps(data_get($exercise, 'steps'), $name, $notes);

                    data_set($exercise, 'steps', $steps);
                    data_set($exercise, 'exercise_media_url', $normalizeExerciseMediaUrl(is_array($exercise) ? $exercise : []));

                    return $exercise;
                })
                ->all());

            return $dayPlan;
        })
        ->all();

    $recommendations = data_get($workout, 'recommendations', []);
    if (! is_array($recommendations)) {
        $recommendations = [];
    }

    $cardioPlan = data_get($workout, 'cardio_plan', []);
    if (! is_array($cardioPlan)) {
        $cardioPlan = [];
    }

    $statusColor = match ((string) $workout->status) {
        'done' => 'success',
        'processing' => 'warning',
        default => 'danger',
    };

    $isWorkoutDone = (string) $workout->status === 'done';
    $isWorkoutActive = (string) ($workout->request_status ?? 'active') === 'active';
    $canRefazer = $isWorkoutDone && $isWorkoutActive;
@endphp

<style>
    .plan-actions-group {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .plan-editor-panel {
        display: none;
        margin-top: 14px;
        border-top: 1px solid var(--border);
        padding-top: 14px;
    }

    .plan-editor-panel.open {
        display: block;
    }

    .plan-refazer-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(19, 18, 32, 0.48);
        z-index: 120;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }

    .plan-refazer-modal.open {
        display: flex;
    }

    .plan-refazer-modal-card {
        width: min(640px, 100%);
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: var(--shadow);
        padding: 14px;
        display: grid;
        gap: 10px;
    }

    .plan-refazer-modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .plan-refazer-modal-head h4 {
        margin: 0;
        font-size: 15px;
    }

    .exercise-preview {
        display: grid;
        gap: 10px;
        margin-top: 8px;
    }

    .exercise-preview-figure {
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        background: #f8fafc;
        padding: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .exercise-preview-figure img,
    .exercise-preview-figure svg {
        width: 100%;
        height: auto;
        display: block;
    }

    .exercise-steps {
        margin: 0;
        padding-left: 18px;
        display: grid;
        gap: 4px;
    }

    .exercise-card-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 8px;
    }

    .exercise-card-thumb {
        margin-top: 8px;
        max-width: 180px;
    }

    .exercise-card-thumb .exercise-preview-figure {
        padding: 6px;
    }

    .exercise-card-thumb img,
    .exercise-card-thumb svg {
        width: auto;
        max-width: 100%;
        max-height: 96px;
        object-fit: contain;
    }

    .catalog-picker-launcher {
        min-width: 280px;
        flex: 1 1 360px;
        display: grid;
        gap: 8px;
    }

    .catalog-picker-launcher .btn {
        justify-content: center;
    }

    .catalog-picker-selection {
        min-height: 72px;
        border: 1px dashed #c6d4e6;
        border-radius: 14px;
        background: #f8fafc;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .catalog-picker-selection strong {
        font-size: 14px;
        color: #102033;
    }

    .catalog-picker-selection small {
        color: #5f7188;
    }

    .catalog-picker-selection.empty {
        display: flex;
        align-items: center;
        color: #6b7280;
    }

    .catalog-picker-selection-media {
        width: 72px;
        height: 72px;
        flex: 0 0 72px;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #d7e3f1;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .catalog-picker-selection-media img,
    .catalog-picker-selection-media svg {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .catalog-picker-selection-body {
        min-width: 0;
        display: grid;
        gap: 4px;
    }

    .catalog-picker-selection-body strong,
    .catalog-picker-selection-body small {
        overflow-wrap: anywhere;
    }

    .exercise-catalog-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.58);
        z-index: 130;
        align-items: center;
        justify-content: center;
        padding: 18px;
    }

    .exercise-catalog-modal.open {
        display: flex;
    }

    .exercise-catalog-modal-card {
        width: min(1080px, 100%);
        max-height: min(88vh, 920px);
        overflow: hidden;
        background: #fff;
        border: 1px solid #dbe3ef;
        border-radius: 20px;
        box-shadow: 0 30px 80px rgba(15, 23, 42, 0.2);
        display: grid;
        grid-template-rows: auto auto 1fr;
    }

    .exercise-catalog-modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 20px 0;
    }

    .exercise-catalog-modal-head h4 {
        margin: 0;
        font-size: 18px;
        color: #102033;
    }

    .exercise-catalog-modal-toolbar {
        padding: 14px 20px 0;
        display: grid;
        gap: 10px;
    }

    .exercise-catalog-modal-filters {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 220px;
        gap: 10px;
        align-items: center;
    }

    .exercise-catalog-modal-toolbar input {
        width: 100%;
    }

    .exercise-catalog-modal-toolbar select {
        width: 100%;
    }

    .catalog-picker-status {
        color: #5f7188;
    }

    .catalog-picker-results {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        overflow: auto;
        padding: 18px 20px 20px;
        align-content: start;
        min-height: 180px;
    }

    .catalog-picker-results:empty {
        display: none;
    }

    .catalog-picker-option {
        width: 100%;
        border: 1px solid #dbe3ef;
        border-radius: 18px;
        background: #fff;
        padding: 12px;
        text-align: left;
        display: grid;
        grid-template-rows: 132px auto;
        gap: 10px;
        cursor: pointer;
        transition: border-color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
    }

    .catalog-picker-option:hover,
    .catalog-picker-option.active,
    .catalog-picker-option:focus-visible {
        border-color: #7aa2d8;
        box-shadow: 0 10px 24px rgba(27, 67, 120, 0.08);
        transform: translateY(-1px);
        outline: none;
    }

    .catalog-picker-option[aria-selected="true"] {
        border-color: #2f6fb3;
        box-shadow: 0 12px 28px rgba(27, 67, 120, 0.14);
    }

    .catalog-picker-option-media {
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid #dbe3ef;
        background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .catalog-picker-option-media img,
    .catalog-picker-option-media svg {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .catalog-picker-option-body {
        display: grid;
        gap: 6px;
        min-width: 0;
    }

    .catalog-picker-option strong {
        font-size: 14px;
        color: #102033;
    }

    .catalog-picker-option small {
        color: #64748b;
    }

    .catalog-picker-option-badge {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        padding: 4px 8px;
        border-radius: 999px;
        background: #e8f1fd;
        color: #24548c;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.02em;
    }

    .catalog-picker-empty {
        margin: 0;
        padding: 0 20px 20px;
        color: #64748b;
    }

    @media (max-width: 720px) {
        .exercise-catalog-modal {
            padding: 10px;
        }

        .exercise-catalog-modal-card {
            max-height: 92vh;
        }

        .catalog-picker-selection {
            align-items: flex-start;
        }

        .catalog-picker-results {
            grid-template-columns: 1fr;
            padding: 14px;
        }

        .exercise-catalog-modal-filters {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="card" id="plan-actions-root">
    <div class="toolbar">
        <div>
            <h3>Plano #{{ $workout->id }}</h3>
            <p>Atualizado em {{ optional($workout->updated_at)?->format('d/m/Y H:i') }}</p>
        </div>
        <div class="plan-actions-group">
            <span class="badge {{ $statusColor }}">{{ strtoupper((string) $workout->status) }}</span>
            @if ((string) $workout->status === 'error')
                <form method="POST" action="{{ route('students.workout.retry', [$workout->id]) }}">
                   @csrf
                   <button class="btn btn-primary" type="submit">Reenviar</button>
                </form>
            @endif

            @if ($activateRoute !== '' && $inactivateRoute !== '')
                @if ($isWorkoutActive)
                    <form method="POST" action="{{ $inactivateRoute }}">
                        @csrf
                        <button type="submit" class="btn btn-soft">Inativar treino</button>
                    </form>
                @else
                    <form method="POST" action="{{ $activateRoute }}">
                        @csrf
                        <button type="submit" class="btn btn-soft">Ativar treino</button>
                    </form>
                    <form method="POST" action="{{ route('students.workout.generate') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Gerar treino</button>
                </form>
                @endif
            @endif

            @if ($editable && $isWorkoutDone)
                <button id="toggle-editor-btn" type="button" class="btn btn-soft" @disabled(! $isWorkoutActive)>Editor manual</button>
            @endif

            @if ($editable && $isWorkoutDone && $regenerateRoute !== '' && $reuseRoute !== '')
                <button id="open-refazer-modal-btn" type="button" class="btn btn-primary" @disabled(! $canRefazer)>Refazer treino</button>
            @endif
        </div>
    </div>

    @if ($editable && $isWorkoutDone && $updateRoute !== '')
        <div id="manual-board-root" class="plan-editor-panel">
            <p style="margin-top: 0;">Arraste os exercicios entre os dias, crie novos exercicios e salve sem consultar a IA.</p>

            @if (! $isWorkoutActive)
                <p style="color: #ea5455; font-weight: 600;">Este treino esta inativo e nao pode ser editado.</p>
            @endif

            <div class="content-stack" style="margin-top: 10px;">
                <div class="actions">
                    <input id="new-day-name" type="text" placeholder="Nome do dia (ex: Quinta)">
                    <input id="new-day-focus" type="text" placeholder="Foco (ex: Pernas)">
                    <button id="add-day-btn" type="button" class="btn btn-soft" @disabled(! $isWorkoutActive)>Adicionar dia</button>
                </div>

                <div class="actions" style="flex-wrap: wrap;">
                    <select id="exercise-day-select" @disabled(! $isWorkoutActive)>
                        <option value="">Escolha o dia</option>
                    </select>
                    <div class="catalog-picker-launcher">
                        <div id="exercise-selected-card" class="catalog-picker-selection empty">Nenhum exercicio selecionado.</div>
                        <button id="open-exercise-catalog-modal-btn" type="button" class="btn btn-soft" @disabled(! $isWorkoutActive)>Buscar exercicio no catalogo</button>
                        <small id="exercise-search-status" class="catalog-picker-status">Abra o catalogo para pesquisar exercicios e visualizar o GIF.</small>
                    </div>
                    <input id="exercise-sets-input" type="number" min="1" max="10" value="3" placeholder="Series">
                    <input id="exercise-reps-input" type="text" value="10-12" placeholder="Reps">
                    <input id="exercise-rest-input" type="text" value="60s" placeholder="Descanso">
                    <select id="exercise-category-input" @disabled(! $isWorkoutActive)>
                        <option value="specific">specific</option>
                        <option value="cardio">cardio</option>
                    </select>
                    <button id="add-exercise-btn" type="button" class="btn btn-primary" @disabled(! $isWorkoutActive)>Criar exercicio</button>
                </div>

                <div id="manual-board-columns" class="kanban-grid"></div>

                <form id="manual-board-form" method="POST" action="{{ $updateRoute }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="manual-weekly-plan-input" name="weekly_plan" value="">
                    <div class="actions">
                        <button type="submit" class="btn btn-primary" @disabled(! $isWorkoutActive)>Salvar board manual</button>
                    </div>
                </form>

                <div id="exercise-catalog-modal" class="exercise-catalog-modal" aria-hidden="true">
                    <div class="exercise-catalog-modal-card">
                        <div class="exercise-catalog-modal-head">
                            <div>
                                <h4>Catalogo de exercicios</h4>
                                <small style="color: #64748b;">Pesquise por nome, foco, equipamento ou WorkoutX name.</small>
                            </div>
                            <button id="close-exercise-catalog-modal-btn" type="button" class="btn btn-soft">Fechar</button>
                        </div>

                        <div class="exercise-catalog-modal-toolbar">
                            <div class="exercise-catalog-modal-filters">
                                <input id="exercise-search-input" type="text" placeholder="Buscar exercicio no catalogo" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="exercise-results" aria-activedescendant="">
                                <select id="exercise-focus-filter">
                                    <option value="">Todos os focos</option>
                                </select>
                            </div>
                            <small id="exercise-catalog-modal-status" class="catalog-picker-status">Digite pelo menos 2 letras para buscar no catalogo.</small>
                        </div>

                        <div>
                            <div id="exercise-results" class="catalog-picker-results" role="listbox"></div>
                            <p id="exercise-results-empty" class="catalog-picker-empty">Nenhum resultado para exibir.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@if ($editable && $isWorkoutDone && $regenerateRoute !== '' && $reuseRoute !== '')
    <div id="plan-refazer-modal" class="plan-refazer-modal" aria-hidden="true">
        <div class="plan-refazer-modal-card">
            <div class="plan-refazer-modal-head">
                <h4>Refazer treino</h4>
                <button id="close-refazer-modal-btn" type="button" class="btn btn-soft">Fechar</button>
            </div>

            <p style="margin: 0;">Escolha como refazer: com IA ou reaproveitando sem IA. Ambos consomem 3 creditos.</p>

            <form method="POST" action="{{ $regenerateRoute }}" class="content-stack">
                @csrf
                <div class="field" style="max-width: 100%;">
                    <label for="adjustment_request">O que deve mudar no treino</label>
                    <textarea id="adjustment_request" name="adjustment_request" rows="3" maxlength="1500" placeholder="Ex: remover mistura de pernas com ombro e manter 4 especificos + 1 cardio por dia." @disabled(! $canRefazer)>{{ old('adjustment_request') }}</textarea>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary" @disabled(! $canRefazer)>Refazer com IA</button>
                </div>
            </form>

            <div class="actions">
                <form method="POST" action="{{ $reuseRoute }}">
                    @csrf
                    <button type="submit" class="btn btn-soft" @disabled(! $canRefazer)>Reaproveitar treino sem IA</button>
                </form>
            </div>
        </div>
    </div>
@endif

<script>
    (function () {
        const toggleEditorButton = document.getElementById('toggle-editor-btn');
        const editorPanel = document.getElementById('manual-board-root');
        const openModalButton = document.getElementById('open-refazer-modal-btn');
        const closeModalButton = document.getElementById('close-refazer-modal-btn');
        const modal = document.getElementById('plan-refazer-modal');

        if (toggleEditorButton && editorPanel) {
            toggleEditorButton.addEventListener('click', function () {
                editorPanel.classList.toggle('open');
            });
        }

        function closeModal() {
            if (!modal) {
                return;
            }

            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }

        if (openModalButton && modal) {
            openModalButton.addEventListener('click', function () {
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
            });
        }

        if (closeModalButton) {
            closeModalButton.addEventListener('click', closeModal);
        }

        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
        }
    })();
</script>

@if ($editable && $isWorkoutDone && $updateRoute !== '')
    <script>
        (function () {
            const boardRoot = document.getElementById('manual-board-root');
            if (!boardRoot) {
                return;
            }

            const initialWeeklyPlan = @json($weeklyPlan, JSON_UNESCAPED_UNICODE);
            const weeklyPlan = Array.isArray(initialWeeklyPlan) ? initialWeeklyPlan : [];
            const isEditable = {{ $isWorkoutActive ? 'true' : 'false' }};
            const catalogSearchRoute = @json($catalogSearchRoute ?? '');

            const columnsContainer = document.getElementById('manual-board-columns');
            const daySelect = document.getElementById('exercise-day-select');
            const hiddenInput = document.getElementById('manual-weekly-plan-input');

            const newDayNameInput = document.getElementById('new-day-name');
            const newDayFocusInput = document.getElementById('new-day-focus');
            const addDayButton = document.getElementById('add-day-btn');

            const openExerciseCatalogModalButton = document.getElementById('open-exercise-catalog-modal-btn');
            const closeExerciseCatalogModalButton = document.getElementById('close-exercise-catalog-modal-btn');
            const exerciseCatalogModal = document.getElementById('exercise-catalog-modal');
            const exerciseSearchInput = document.getElementById('exercise-search-input');
            const exerciseFocusFilter = document.getElementById('exercise-focus-filter');
            const exerciseResults = document.getElementById('exercise-results');
            const exerciseResultsEmpty = document.getElementById('exercise-results-empty');
            const exerciseSelectedCard = document.getElementById('exercise-selected-card');
            const exerciseSearchStatus = document.getElementById('exercise-search-status');
            const exerciseCatalogModalStatus = document.getElementById('exercise-catalog-modal-status');
            const exerciseSetsInput = document.getElementById('exercise-sets-input');
            const exerciseRepsInput = document.getElementById('exercise-reps-input');
            const exerciseRestInput = document.getElementById('exercise-rest-input');
            const exerciseCategoryInput = document.getElementById('exercise-category-input');
            const addExerciseButton = document.getElementById('add-exercise-btn');

            const boardForm = document.getElementById('manual-board-form');
            let catalogSearchAbortController = null;
            let selectedCatalogExercise = null;
            let latestCatalogResults = [];
            let highlightedCatalogIndex = -1;
            let searchDebounceTimer = null;
            let availableCatalogFocuses = [];

            function renderExerciseMedia(mediaUrl, svgMarkup, fallbackUrl) {
                if (String(mediaUrl || '').trim() !== '') {
                    const safeMediaUrl = String(mediaUrl).replace(/"/g, '&quot;');
                    const safeFallbackUrl = String(fallbackUrl || '').trim().replace(/"/g, '&quot;');

                    return '<img src="' + safeMediaUrl + '" alt="Midia do exercicio" loading="lazy"' + (safeFallbackUrl !== '' ? ' data-fallback-src="' + safeFallbackUrl + '"' : '') + '>';
                }

                return String(svgMarkup || '');
            }

            function normalizeCatalogText(value) {
                return String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .trim()
                    .toLowerCase();
            }

            function resolveCatalogPreviewUrl(exercise) {
                const mediaUrl = String(exercise && exercise.exercise_media_url ? exercise.exercise_media_url : '').trim();
                if (mediaUrl !== '') {
                    return mediaUrl;
                }

                return String(exercise && exercise.remote_gif_url ? exercise.remote_gif_url : '').trim();
            }

            function getFallbackPreviewUrl(exercise) {
                return String(exercise && exercise.remote_gif_url ? exercise.remote_gif_url : '').trim();
            }

            function setSearchStatus(message) {
                if (exerciseSearchStatus) {
                    exerciseSearchStatus.textContent = message;
                }

                if (exerciseCatalogModalStatus) {
                    exerciseCatalogModalStatus.textContent = message;
                }
            }

            function updateResultsEmptyState() {
                if (!exerciseResultsEmpty) {
                    return;
                }

                const hasResults = Array.isArray(latestCatalogResults) && latestCatalogResults.length > 0;
                exerciseResultsEmpty.style.display = hasResults ? 'none' : 'block';
            }

            function renderFocusOptions() {
                if (!exerciseFocusFilter) {
                    return;
                }

                const previousValue = String(exerciseFocusFilter.value || '').trim();
                const normalizedFocuses = Array.isArray(availableCatalogFocuses)
                    ? availableCatalogFocuses.map(function (focus) {
                        return String(focus || '').trim();
                    }).filter(function (focus) {
                        return focus !== '';
                    })
                    : [];

                exerciseFocusFilter.innerHTML = '';

                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Todos os focos';
                exerciseFocusFilter.appendChild(defaultOption);

                normalizedFocuses.forEach(function (focus) {
                    const option = document.createElement('option');
                    option.value = focus;
                    option.textContent = focus.charAt(0).toUpperCase() + focus.slice(1);
                    exerciseFocusFilter.appendChild(option);
                });

                exerciseFocusFilter.value = normalizedFocuses.indexOf(previousValue) >= 0 ? previousValue : '';
            }

            function currentSelectedDayPlan() {
                const dayIndex = Number(daySelect && daySelect.value ? daySelect.value : '');

                if (!Number.isInteger(dayIndex) || !weeklyPlan[dayIndex]) {
                    return null;
                }

                return weeklyPlan[dayIndex];
            }

            function syncFocusFilterWithSelectedDay() {
                if (!exerciseFocusFilter) {
                    return;
                }

                const selectedDayPlan = currentSelectedDayPlan();
                const dayFocus = normalizeCatalogText(selectedDayPlan && selectedDayPlan.focus ? selectedDayPlan.focus : '');

                if (dayFocus === '') {
                    return;
                }

                let matchingFocus = '';
                const optionValues = Array.from(exerciseFocusFilter.options).map(function (option) {
                    return String(option.value || '').trim();
                }).filter(function (value) {
                    return value !== '';
                });

                optionValues.some(function (focus) {
                    const normalizedFocus = normalizeCatalogText(focus);
                    if (normalizedFocus === dayFocus || dayFocus.indexOf(normalizedFocus) >= 0 || normalizedFocus.indexOf(dayFocus) >= 0) {
                        matchingFocus = focus;
                        return true;
                    }

                    return false;
                });

                if (matchingFocus === '') {
                    matchingFocus = dayFocus;

                    const alreadyExists = optionValues.some(function (focus) {
                        return normalizeCatalogText(focus) === matchingFocus;
                    });

                    if (!alreadyExists) {
                        const option = document.createElement('option');
                        option.value = matchingFocus;
                        option.textContent = String(selectedDayPlan.focus || 'Foco do dia').trim();
                        exerciseFocusFilter.appendChild(option);
                    }
                }

                exerciseFocusFilter.value = matchingFocus;
            }

            function openCatalogModal() {
                if (!exerciseCatalogModal || !isEditable) {
                    return;
                }

                exerciseCatalogModal.classList.add('open');
                exerciseCatalogModal.setAttribute('aria-hidden', 'false');

                if (exerciseSearchInput) {
                    window.setTimeout(function () {
                        exerciseSearchInput.focus();
                        exerciseSearchInput.select();
                    }, 20);
                }
            }

            function closeCatalogModal() {
                if (!exerciseCatalogModal) {
                    return;
                }

                exerciseCatalogModal.classList.remove('open');
                exerciseCatalogModal.setAttribute('aria-hidden', 'true');
            }

            function setResultsExpanded(isExpanded) {
                if (!exerciseSearchInput) {
                    return;
                }

                exerciseSearchInput.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
            }

            function updateHighlightedOption(scrollIntoView) {
                if (!exerciseResults) {
                    return;
                }

                const optionElements = Array.from(exerciseResults.querySelectorAll('.catalog-picker-option'));

                optionElements.forEach(function (optionElement, index) {
                    const isActive = index === highlightedCatalogIndex;
                    optionElement.classList.toggle('active', isActive);

                    if (isActive) {
                        exerciseSearchInput?.setAttribute('aria-activedescendant', optionElement.id);

                        if (scrollIntoView) {
                            optionElement.scrollIntoView({ block: 'nearest' });
                        }
                    }
                });

                if (highlightedCatalogIndex < 0 || highlightedCatalogIndex >= optionElements.length) {
                    exerciseSearchInput?.setAttribute('aria-activedescendant', '');
                }
            }

            function resetCatalogResults() {
                latestCatalogResults = [];
                highlightedCatalogIndex = -1;
                setResultsExpanded(false);
                exerciseSearchInput?.setAttribute('aria-activedescendant', '');
                updateResultsEmptyState();
            }

            function selectCatalogExercise(exercise) {
                selectedCatalogExercise = exercise;
                renderSelectedExercise(selectedCatalogExercise);

                if (exerciseResults) {
                    exerciseResults.innerHTML = '';
                }

                resetCatalogResults();

                setSearchStatus('Exercicio selecionado. Ajuste series, reps e descanso para adicionar.');
                closeCatalogModal();
            }

            function renderSelectedExercise(exercise) {
                if (!exerciseSelectedCard) {
                    return;
                }

                if (!exercise) {
                    exerciseSelectedCard.classList.add('empty');
                    exerciseSelectedCard.innerHTML = 'Nenhum exercicio selecionado.';
                    return;
                }

                exerciseSelectedCard.classList.remove('empty');
                exerciseSelectedCard.innerHTML = '';

                if (String(exercise.preview_url || '').trim() !== '') {
                    const preview = document.createElement('div');
                    preview.className = 'catalog-picker-selection-media';
                    preview.innerHTML = renderExerciseMedia(exercise.preview_url, '', exercise.preview_fallback_url || '');
                    exerciseSelectedCard.appendChild(preview);
                }

                const body = document.createElement('div');
                body.className = 'catalog-picker-selection-body';

                const title = document.createElement('strong');
                title.textContent = String(exercise.name || 'Exercicio').trim();

                const meta = document.createElement('small');
                meta.textContent = [
                    String(exercise.focus || '').trim(),
                    String(exercise.equipment || '').trim(),
                    String(exercise.workoutx_name || '').trim(),
                ].filter(function (part) {
                    return part !== '';
                }).join(' | ');

                body.appendChild(title);

                if (meta.textContent !== '') {
                    body.appendChild(meta);
                }

                if (String(exercise.workoutx_name || '').trim() !== '') {
                    const workoutxName = document.createElement('small');
                    workoutxName.textContent = 'WorkoutX: ' + String(exercise.workoutx_name).trim();
                    body.appendChild(workoutxName);
                }

                exerciseSelectedCard.appendChild(body);
            }

            function setExerciseOptions(items) {
                if (!exerciseResults) {
                    return;
                }

                exerciseResults.innerHTML = '';
                latestCatalogResults = items.slice();
                highlightedCatalogIndex = items.length > 0 ? 0 : -1;
                setResultsExpanded(items.length > 0);

                items.forEach(function (item) {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'catalog-picker-option';

                    const previewUrl = resolveCatalogPreviewUrl(item);
                    const fallbackPreviewUrl = getFallbackPreviewUrl(item);

                    const catalogExercise = {
                        id: String(item.id || '').trim(),
                        name: String(item.localized_name_pt_br || item.name || '').trim(),
                        workoutx_name: String(item.workoutx_name || '').trim(),
                        focus: String(item.focus || '').trim(),
                        equipment: String(item.equipment || '').trim(),
                        target: String(item.target || '').trim(),
                        preview_url: previewUrl,
                        preview_fallback_url: fallbackPreviewUrl,
                        storage_path: String(item.storage_path || '').trim(),
                        remote_gif_url: String(item.remote_gif_url || '').trim(),
                    };

                    option.id = 'exercise-result-option-' + catalogExercise.id + '-' + exerciseResults.childElementCount;
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');

                    const media = document.createElement('div');
                    media.className = 'catalog-picker-option-media';
                    media.innerHTML = previewUrl !== ''
                        ? renderExerciseMedia(previewUrl, '', fallbackPreviewUrl)
                        : '<span style="padding: 12px; color: #64748b; font-size: 12px; text-align: center;">Sem preview</span>';

                    const body = document.createElement('div');
                    body.className = 'catalog-picker-option-body';

                    const title = document.createElement('strong');
                    title.textContent = catalogExercise.name || 'Exercicio';

                    const meta = document.createElement('small');
                    meta.textContent = [
                        catalogExercise.focus,
                        catalogExercise.target,
                        catalogExercise.equipment,
                    ].filter(function (part) {
                        return part !== '';
                    }).join(' | ');

                    const workoutxName = document.createElement('small');
                    workoutxName.textContent = catalogExercise.workoutx_name;

                    const badge = document.createElement('span');
                    badge.className = 'catalog-picker-option-badge';
                    badge.textContent = catalogExercise.storage_path !== ''
                        ? 'GIF local'
                        : (catalogExercise.remote_gif_url !== '' ? 'GIF remoto' : 'Sem GIF');

                    body.appendChild(title);

                    if (meta.textContent !== '') {
                        body.appendChild(meta);
                    }

                    if (workoutxName.textContent !== '') {
                        body.appendChild(workoutxName);
                    }

                    body.appendChild(badge);

                    option.appendChild(media);
                    option.appendChild(body);

                    option.addEventListener('click', function () {
                        selectCatalogExercise(catalogExercise);
                    });

                    exerciseResults.appendChild(option);
                });

                updateResultsEmptyState();
                updateHighlightedOption(false);
            }

            async function searchCatalog(term) {
                if (!exerciseResults || String(catalogSearchRoute || '').trim() === '') {
                    return;
                }

                const trimmedTerm = String(term || '').trim();
                const selectedFocus = String(exerciseFocusFilter && exerciseFocusFilter.value ? exerciseFocusFilter.value : '').trim();

                if (trimmedTerm.length < 2) {
                    setExerciseOptions([]);
                    resetCatalogResults();
                    setSearchStatus(selectedFocus !== ''
                        ? 'Digite pelo menos 2 letras para buscar dentro do foco selecionado.'
                        : 'Digite pelo menos 2 letras para buscar no catalogo.');
                    return;
                }

                if (catalogSearchAbortController) {
                    catalogSearchAbortController.abort();
                }

                catalogSearchAbortController = new AbortController();
                setSearchStatus('Buscando exercicios...');

                try {
                    const query = new URLSearchParams({
                        search: trimmedTerm,
                        limit: '12',
                    });

                    if (selectedFocus !== '') {
                        query.set('focus', selectedFocus);
                    }

                    const response = await fetch(catalogSearchRoute + '?' + query.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        signal: catalogSearchAbortController.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Falha ao buscar catalogo');
                    }

                    const payload = await response.json();
                    const items = Array.isArray(payload.data) ? payload.data : [];
                    availableCatalogFocuses = Array.isArray(payload.meta && payload.meta.available_focuses)
                        ? payload.meta.available_focuses
                        : availableCatalogFocuses;
                    renderFocusOptions();

                    setExerciseOptions(items);
                    setSearchStatus(items.length > 0
                        ? items.length + ' exercicio(s) encontrado(s).'
                        : 'Nenhum exercicio encontrado para esta busca.');
                } catch (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }

                    setExerciseOptions([]);
                    resetCatalogResults();
                    setSearchStatus('Nao foi possivel buscar o catalogo agora.');
                }
            }

            function scheduleCatalogSearch(term) {
                if (searchDebounceTimer) {
                    clearTimeout(searchDebounceTimer);
                }

                const trimmedTerm = String(term || '').trim();

                if (trimmedTerm.length < 2) {
                    searchCatalog(trimmedTerm);
                    return;
                }

                setSearchStatus('Digitando...');

                searchDebounceTimer = setTimeout(function () {
                    searchCatalog(trimmedTerm);
                }, 220);
            }

            function moveHighlightedCatalogOption(direction) {
                if (latestCatalogResults.length === 0) {
                    return;
                }

                if (highlightedCatalogIndex < 0) {
                    highlightedCatalogIndex = 0;
                } else {
                    highlightedCatalogIndex = (highlightedCatalogIndex + direction + latestCatalogResults.length) % latestCatalogResults.length;
                }

                updateHighlightedOption(true);
            }

            function selectHighlightedCatalogOption() {
                if (highlightedCatalogIndex < 0 || highlightedCatalogIndex >= latestCatalogResults.length) {
                    return;
                }

                const highlightedExercise = latestCatalogResults[highlightedCatalogIndex];
                if (!highlightedExercise) {
                    return;
                }

                selectCatalogExercise({
                    id: String(highlightedExercise.id || '').trim(),
                    name: String(highlightedExercise.localized_name_pt_br || highlightedExercise.name || '').trim(),
                    workoutx_name: String(highlightedExercise.workoutx_name || '').trim(),
                    focus: String(highlightedExercise.focus || '').trim(),
                    equipment: String(highlightedExercise.equipment || '').trim(),
                    preview_url: resolveCatalogPreviewUrl(highlightedExercise),
                    preview_fallback_url: getFallbackPreviewUrl(highlightedExercise),
                });
            }

            function sanitizeExercise(exercise) {
                const sets = Number(exercise.sets || 3);
                const normalizedSets = Number.isFinite(sets) ? Math.min(10, Math.max(1, sets)) : 3;

                const category = String(exercise.category || 'specific').toLowerCase();
                const normalizedCategory = category === 'cardio' ? 'cardio' : 'specific';
                const steps = Array.isArray(exercise.steps)
                    ? exercise.steps.map(function (step) {
                        return String(step || '').trim();
                    }).filter(function (step) {
                        return step !== '';
                    }).slice(0, 5)
                    : [];

                return {
                    name: String(exercise.name || '').trim(),
                    category: normalizedCategory,
                    sets: normalizedSets,
                    reps: String(exercise.reps || '10-12').trim(),
                    rest: String(exercise.rest || '60s').trim(),
                    notes: String(exercise.notes || '').trim(),
                    steps: steps,
                    remote_exercise_id: String(exercise.remote_exercise_id || '').trim(),
                    workoutx_name: String(exercise.workoutx_name || '').trim(),
                    exercise_media_path: String(exercise.exercise_media_path || '').trim(),
                    exercise_media_url: String(exercise.exercise_media_url || '').trim(),
                    illustration_svg: typeof exercise.illustration_svg === 'string' ? exercise.illustration_svg.trim() : '',
                };
            }

            function sanitizeDay(day, index) {
                const exercises = Array.isArray(day.exercises) ? day.exercises.map(sanitizeExercise).filter(function (exercise) {
                    return exercise.name !== '';
                }) : [];

                return {
                    day: String(day.day || ('Dia ' + (index + 1))).trim() || ('Dia ' + (index + 1)),
                    focus: String(day.focus || 'Treino geral').trim() || 'Treino geral',
                    exercises: exercises,
                };
            }

            function normalizePlan() {
                return weeklyPlan.map(function (day, index) {
                    return sanitizeDay(day, index);
                }).filter(function (day) {
                    return Array.isArray(day.exercises);
                });
            }

            function syncHiddenInput() {
                hiddenInput.value = JSON.stringify(normalizePlan());
            }

            function renderDaySelect() {
                daySelect.innerHTML = '';

                const placeholderOption = document.createElement('option');
                placeholderOption.value = '';
                placeholderOption.textContent = 'Escolha o dia';
                daySelect.appendChild(placeholderOption);

                weeklyPlan.forEach(function (dayPlan, dayIndex) {
                    const option = document.createElement('option');
                    option.value = String(dayIndex);
                    option.textContent = String(dayPlan.day || ('Dia ' + (dayIndex + 1))) + ' - ' + String(dayPlan.focus || 'Treino geral');
                    daySelect.appendChild(option);
                });
            }

            function onDragStart(event) {
                const sourceDayIndex = event.currentTarget.getAttribute('data-day-index');
                const sourceExerciseIndex = event.currentTarget.getAttribute('data-exercise-index');
                const payload = JSON.stringify({
                    sourceDayIndex: Number(sourceDayIndex),
                    sourceExerciseIndex: Number(sourceExerciseIndex),
                });

                event.dataTransfer.setData('application/json', payload);
                event.dataTransfer.effectAllowed = 'move';
            }

            function onDragOver(event) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
            }

            function onDrop(event) {
                event.preventDefault();
                if (!isEditable) {
                    return;
                }

                const targetDayIndex = Number(event.currentTarget.getAttribute('data-target-day-index'));
                const data = event.dataTransfer.getData('application/json');

                if (!data) {
                    return;
                }

                let parsed;
                try {
                    parsed = JSON.parse(data);
                } catch (_) {
                    return;
                }

                const sourceDayIndex = Number(parsed.sourceDayIndex);
                const sourceExerciseIndex = Number(parsed.sourceExerciseIndex);

                if (!Number.isInteger(sourceDayIndex) || !Number.isInteger(sourceExerciseIndex) || !Number.isInteger(targetDayIndex)) {
                    return;
                }

                if (!weeklyPlan[sourceDayIndex] || !weeklyPlan[targetDayIndex]) {
                    return;
                }

                const sourceExercises = weeklyPlan[sourceDayIndex].exercises;
                const targetExercises = weeklyPlan[targetDayIndex].exercises;

                if (!Array.isArray(sourceExercises) || !Array.isArray(targetExercises) || !sourceExercises[sourceExerciseIndex]) {
                    return;
                }

                const movedExercise = sourceExercises.splice(sourceExerciseIndex, 1)[0];
                targetExercises.push(movedExercise);

                renderBoard();
            }

            function removeExercise(dayIndex, exerciseIndex) {
                if (!isEditable) {
                    return;
                }

                const dayPlan = weeklyPlan[dayIndex];
                if (!dayPlan || !Array.isArray(dayPlan.exercises)) {
                    return;
                }

                dayPlan.exercises.splice(exerciseIndex, 1);
                renderBoard();
            }

            function renderBoard() {
                columnsContainer.innerHTML = '';

                weeklyPlan.forEach(function (dayPlan, dayIndex) {
                    if (!Array.isArray(dayPlan.exercises)) {
                        dayPlan.exercises = [];
                    }

                    const column = document.createElement('article');
                    column.className = 'kanban-column';

                    const header = document.createElement('header');
                    const title = document.createElement('h4');
                    title.textContent = String(dayPlan.day || ('Dia ' + (dayIndex + 1)));
                    const focus = document.createElement('p');
                    focus.textContent = String(dayPlan.focus || 'Treino geral');
                    header.appendChild(title);
                    header.appendChild(focus);

                    const cards = document.createElement('div');
                    cards.className = 'kanban-cards';
                    cards.setAttribute('data-target-day-index', String(dayIndex));
                    cards.addEventListener('dragover', onDragOver);
                    cards.addEventListener('drop', onDrop);

                    dayPlan.exercises.forEach(function (exercise, exerciseIndex) {
                        const safeExercise = sanitizeExercise(exercise);
                        const card = document.createElement('div');
                        card.className = 'mini-card';
                        card.setAttribute('draggable', isEditable ? 'true' : 'false');
                        card.setAttribute('data-day-index', String(dayIndex));
                        card.setAttribute('data-exercise-index', String(exerciseIndex));

                        if (isEditable) {
                            card.addEventListener('dragstart', onDragStart);
                        }

                        const strong = document.createElement('strong');
                        strong.textContent = safeExercise.name;

                        const category = document.createElement('small');
                        category.textContent = 'Categoria: ' + safeExercise.category;

                        const meta = document.createElement('small');
                        meta.textContent = 'Series: ' + safeExercise.sets + ' | Reps: ' + safeExercise.reps + ' | Descanso: ' + safeExercise.rest;

                        const notes = document.createElement('small');
                        notes.textContent = safeExercise.notes || '';

                        const steps = document.createElement('small');
                        steps.textContent = safeExercise.steps.length > 0
                            ? 'Passos: ' + safeExercise.steps.length
                            : 'Passos serao gerados automaticamente';

                        card.appendChild(strong);
                        card.appendChild(category);
                        card.appendChild(meta);
                        card.appendChild(notes);
                        card.appendChild(steps);

                        if (safeExercise.exercise_media_url || safeExercise.illustration_svg) {
                            const preview = document.createElement('div');
                            preview.className = 'exercise-card-thumb';

                            const figure = document.createElement('div');
                            figure.className = 'exercise-preview-figure';
                            figure.innerHTML = renderExerciseMedia(safeExercise.exercise_media_url, safeExercise.illustration_svg);

                            preview.appendChild(figure);
                            card.appendChild(preview);
                        }

                        if (isEditable) {
                            const removeButton = document.createElement('button');
                            removeButton.type = 'button';
                            removeButton.className = 'btn btn-soft';
                            removeButton.textContent = 'Remover';
                            removeButton.style.marginTop = '6px';
                            removeButton.addEventListener('click', function () {
                                removeExercise(dayIndex, exerciseIndex);
                            });
                            card.appendChild(removeButton);
                        }

                        cards.appendChild(card);
                    });

                    if (dayPlan.exercises.length === 0) {
                        const empty = document.createElement('div');
                        empty.className = 'mini-card';
                        const emptyText = document.createElement('small');
                        emptyText.textContent = 'Sem exercicios.';
                        empty.appendChild(emptyText);
                        cards.appendChild(empty);
                    }

                    column.appendChild(header);
                    column.appendChild(cards);
                    columnsContainer.appendChild(column);
                });

                renderDaySelect();
                syncHiddenInput();
            }

            if (addDayButton) {
                addDayButton.addEventListener('click', function () {
                    if (!isEditable) {
                        return;
                    }

                    const dayName = String(newDayNameInput.value || '').trim();
                    const dayFocus = String(newDayFocusInput.value || '').trim();

                    if (dayName === '') {
                        return;
                    }

                    weeklyPlan.push({
                        day: dayName,
                        focus: dayFocus || 'Treino geral',
                        exercises: [],
                    });

                    newDayNameInput.value = '';
                    newDayFocusInput.value = '';
                    renderBoard();
                });
            }

            if (addExerciseButton) {
                addExerciseButton.addEventListener('click', function () {
                    if (!isEditable) {
                        return;
                    }

                    const dayIndex = Number(daySelect.value);
                    if (!Number.isInteger(dayIndex) || !weeklyPlan[dayIndex]) {
                        return;
                    }

                    const name = String(selectedCatalogExercise && selectedCatalogExercise.name ? selectedCatalogExercise.name : '').trim();
                    if (name === '') {
                        return;
                    }

                    const newExercise = sanitizeExercise({
                        name: name,
                        category: String(exerciseCategoryInput.value || 'specific'),
                        sets: Number(exerciseSetsInput.value || 3),
                        reps: String(exerciseRepsInput.value || '10-12'),
                        rest: String(exerciseRestInput.value || '60s'),
                        notes: '',
                        steps: [],
                        remote_exercise_id: String(selectedCatalogExercise && selectedCatalogExercise.id ? selectedCatalogExercise.id : '').trim(),
                        workoutx_name: String(selectedCatalogExercise && selectedCatalogExercise.workoutx_name ? selectedCatalogExercise.workoutx_name : '').trim(),
                        exercise_media_path: String(selectedCatalogExercise && selectedCatalogExercise.storage_path ? selectedCatalogExercise.storage_path : '').trim(),
                        exercise_media_url: String(selectedCatalogExercise && selectedCatalogExercise.preview_url ? selectedCatalogExercise.preview_url : '').trim(),
                        illustration_svg: '',
                    });

                    weeklyPlan[dayIndex].exercises.push(newExercise);

                    if (exerciseSearchInput) {
                        exerciseSearchInput.value = '';
                    }

                    selectedCatalogExercise = null;
                    renderSelectedExercise(null);
                    setExerciseOptions([]);
                    resetCatalogResults();
                    setSearchStatus('Abra o catalogo para pesquisar exercicios e visualizar o GIF.');
                    exerciseSetsInput.value = '3';
                    exerciseRepsInput.value = '10-12';
                    exerciseRestInput.value = '60s';
                    exerciseCategoryInput.value = 'specific';

                    renderBoard();
                });
            }

            if (boardForm) {
                boardForm.addEventListener('submit', function () {
                    syncHiddenInput();
                });
            }

            if (exerciseSearchInput) {
                exerciseSearchInput.addEventListener('input', function (event) {
                    scheduleCatalogSearch(event.target.value);
                });

                exerciseSearchInput.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        moveHighlightedCatalogOption(1);
                        return;
                    }

                    if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        moveHighlightedCatalogOption(-1);
                        return;
                    }

                    if (event.key === 'Enter' && latestCatalogResults.length > 0) {
                        event.preventDefault();
                        selectHighlightedCatalogOption();
                        return;
                    }

                    if (event.key === 'Escape') {
                        event.preventDefault();
                        if (exerciseResults) {
                            exerciseResults.innerHTML = '';
                        }
                        resetCatalogResults();
                        setSearchStatus('Busca fechada.');
                        closeCatalogModal();
                    }
                });

                exerciseSearchInput.addEventListener('focus', function () {
                    if (latestCatalogResults.length > 0) {
                        setResultsExpanded(true);
                        updateHighlightedOption(false);
                    }
                });
            }

            if (exerciseFocusFilter) {
                exerciseFocusFilter.addEventListener('change', function () {
                    scheduleCatalogSearch(exerciseSearchInput ? exerciseSearchInput.value : '');
                });
            }

            if (openExerciseCatalogModalButton) {
                openExerciseCatalogModalButton.addEventListener('click', function () {
                    syncFocusFilterWithSelectedDay();
                    if (exerciseSearchInput && String(exerciseSearchInput.value || '').trim().length >= 2) {
                        scheduleCatalogSearch(exerciseSearchInput.value);
                    }
                    openCatalogModal();
                });
            }

            if (daySelect) {
                daySelect.addEventListener('change', function () {
                    if (exerciseCatalogModal && exerciseCatalogModal.classList.contains('open')) {
                        syncFocusFilterWithSelectedDay();
                        scheduleCatalogSearch(exerciseSearchInput ? exerciseSearchInput.value : '');
                    }
                });
            }

            document.addEventListener('error', function (event) {
                const target = event.target;

                if (!(target instanceof HTMLImageElement)) {
                    return;
                }

                const fallbackSrc = String(target.getAttribute('data-fallback-src') || '').trim();
                if (fallbackSrc === '' || target.src === fallbackSrc) {
                    return;
                }

                target.src = fallbackSrc;
            }, true);

            if (closeExerciseCatalogModalButton) {
                closeExerciseCatalogModalButton.addEventListener('click', function () {
                    closeCatalogModal();
                });
            }

            if (exerciseCatalogModal) {
                exerciseCatalogModal.addEventListener('click', function (event) {
                    if (event.target === exerciseCatalogModal) {
                        closeCatalogModal();
                    }
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && exerciseCatalogModal && exerciseCatalogModal.classList.contains('open')) {
                    closeCatalogModal();
                }
            });

            renderBoard();
            renderFocusOptions();
            renderSelectedExercise(null);
            setExerciseOptions([]);
            setSearchStatus('Abra o catalogo para pesquisar exercicios e visualizar o GIF.');
        })();
    </script>
@endif

@if ((string) $workout->status !== 'done')
    <div class="card">
        <h3>Plano em processamento</h3>
        <p>O sistema ainda esta montando treino, midias e recomendacoes. Atualize a pagina em instantes.</p>
    </div>
@else
    <div class="content-stack">
        <div class="card">
            <h3>Treino semanal (kanban)</h3>
            <p>Visualizacao por dia para facilitar acompanhamento.</p>
            <div class="kanban-grid" style="margin-top: 14px;">
                @forelse ($weeklyPlan as $dayPlan)
                    <article class="kanban-column">
                        <header>
                            <h4>{{ (string) data_get($dayPlan, 'day', 'Dia') }}</h4>
                            <p>{{ (string) data_get($dayPlan, 'focus', 'Treino geral') }}</p>
                        </header>

                        @php
                            $exercises = data_get($dayPlan, 'exercises', []);
                            if (! is_array($exercises)) {
                                $exercises = [];
                            }
                        @endphp

                        <div class="kanban-cards">
                            @forelse ($exercises as $exercise)
                                @php
                                    $exerciseSteps = data_get($exercise, 'steps', []);
                                    if (! is_array($exerciseSteps)) {
                                        $exerciseSteps = [];
                                    }

                                    $exerciseSvg = trim((string) data_get($exercise, 'illustration_svg', ''));
                                    $exerciseMediaUrl = trim((string) data_get($exercise, 'exercise_media_url', ''));
                                @endphp
                                <div class="mini-card">
                                    <strong>{{ (string) data_get($exercise, 'name', 'Exercicio') }}</strong>
                                    <small>Series: {{ (string) data_get($exercise, 'sets', '-') }} | Reps: {{ (string) data_get($exercise, 'reps', '-') }}</small>
                                    <small>Descanso: {{ (string) data_get($exercise, 'rest', '-') }}</small>
                                    <small>{{ (string) data_get($exercise, 'notes', '') }}</small>

                                    @if ($exerciseMediaUrl !== '' || $exerciseSvg !== '')
                                        <div class="exercise-preview exercise-card-thumb">
                                            <div class="exercise-preview-figure">
                                                @if ($exerciseMediaUrl !== '')
                                                    <img src="{{ $exerciseMediaUrl }}" alt="Midia do exercicio {{ (string) data_get($exercise, 'name', 'Exercicio') }}" loading="lazy">
                                                @else
                                                    {!! $exerciseSvg !!}
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    @if ($exerciseSteps !== [])
                                        <ol class="exercise-steps">
                                            @foreach ($exerciseSteps as $step)
                                                <li><small>{{ (string) $step }}</small></li>
                                            @endforeach
                                        </ol>
                                    @endif
                                </div>
                            @empty
                                <div class="mini-card">
                                    <small>Sem exercicios informados.</small>
                                </div>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <div class="card">
                        <p>Nao ha treino semanal registrado.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="stats">
            <div class="card">
                <h3>Recomendacoes</h3>
                <div class="stack-list" style="margin-top: 10px;">
                    @forelse ($recommendations as $recommendation)
                        <div class="mini-card">
                            <small>{{ (string) $recommendation }}</small>
                        </div>
                    @empty
                        <p>Sem recomendacoes para este plano.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <h3>Cardio</h3>
                <div class="stack-list" style="margin-top: 10px;">
                    @forelse ($cardioPlan as $cardio)
                        <div class="mini-card">
                            <strong>{{ (string) data_get($cardio, 'type', 'Cardio') }}</strong>
                            <small>Duracao: {{ (string) data_get($cardio, 'duration', '-') }}</small>
                            <small>Frequencia: {{ (string) data_get($cardio, 'frequency', '-') }}</small>
                        </div>
                    @empty
                        <p>Sem cardio definido.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endif
