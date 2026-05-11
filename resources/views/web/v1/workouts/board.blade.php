@php
    $exerciseAssetBuilder = app(\App\Support\Workout\ExerciseAssetBuilder::class);
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
        ->map(function ($dayPlan) use ($exerciseAssetBuilder) {
            $exercises = data_get($dayPlan, 'exercises', []);

            if (! is_array($exercises)) {
                $exercises = [];
            }

            data_set($dayPlan, 'exercises', collect($exercises)
                ->map(function ($exercise) use ($exerciseAssetBuilder) {
                    $name = trim((string) data_get($exercise, 'name', 'Exercicio'));
                    $notes = trim((string) data_get($exercise, 'notes', ''));
                    $steps = $exerciseAssetBuilder->normalizeSteps(data_get($exercise, 'steps'), $name, $notes);

                    data_set($exercise, 'steps', $steps);

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

    .catalog-picker {
        min-width: 280px;
        flex: 1 1 360px;
        display: grid;
        gap: 8px;
        padding: 12px;
        border: 1px solid #dbe3ef;
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }

    .catalog-picker input {
        width: 100%;
    }

    .catalog-picker-selection {
        min-height: 52px;
        border: 1px dashed #c6d4e6;
        border-radius: 14px;
        background: #f8fafc;
        padding: 10px 12px;
        display: grid;
        gap: 4px;
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

    .catalog-picker-results {
        display: grid;
        gap: 8px;
        max-height: 240px;
        overflow: auto;
        padding-right: 2px;
    }

    .catalog-picker-results:empty {
        display: none;
    }

    .catalog-picker-option {
        width: 100%;
        border: 1px solid #dbe3ef;
        border-radius: 14px;
        background: #fff;
        padding: 10px 12px;
        text-align: left;
        display: grid;
        gap: 4px;
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

    .catalog-picker-option strong {
        font-size: 14px;
        color: #102033;
    }

    .catalog-picker-option small {
        color: #64748b;
    }

    .catalog-picker-status {
        color: #5f7188;
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
                    <div class="catalog-picker">
                        <input id="exercise-search-input" type="text" placeholder="Buscar exercicio no catalogo" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="exercise-results" aria-activedescendant="">
                        <div id="exercise-selected-card" class="catalog-picker-selection empty">Nenhum exercicio selecionado.</div>
                        <div id="exercise-results" class="catalog-picker-results" role="listbox"></div>
                        <small id="exercise-search-status" class="catalog-picker-status">Digite pelo menos 2 letras para buscar no catalogo.</small>
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

            const exerciseSearchInput = document.getElementById('exercise-search-input');
            const exerciseResults = document.getElementById('exercise-results');
            const exerciseSelectedCard = document.getElementById('exercise-selected-card');
            const exerciseSearchStatus = document.getElementById('exercise-search-status');
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
            }

            function selectCatalogExercise(exercise) {
                selectedCatalogExercise = exercise;
                renderSelectedExercise(selectedCatalogExercise);

                if (exerciseResults) {
                    exerciseResults.innerHTML = '';
                }

                resetCatalogResults();

                if (exerciseSearchStatus) {
                    exerciseSearchStatus.textContent = 'Exercicio selecionado. Ajuste series, reps e descanso para adicionar.';
                }
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

                exerciseSelectedCard.appendChild(title);

                if (meta.textContent !== '') {
                    exerciseSelectedCard.appendChild(meta);
                }
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

                    const catalogExercise = {
                        id: String(item.id || '').trim(),
                        name: String(item.localized_name_pt_br || item.name || '').trim(),
                        workoutx_name: String(item.workoutx_name || '').trim(),
                        focus: String(item.focus || '').trim(),
                        equipment: String(item.equipment || '').trim(),
                    };

                    option.id = 'exercise-result-option-' + catalogExercise.id + '-' + exerciseResults.childElementCount;
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');

                    const title = document.createElement('strong');
                    title.textContent = catalogExercise.name || 'Exercicio';

                    const meta = document.createElement('small');
                    meta.textContent = [
                        catalogExercise.focus,
                        catalogExercise.equipment,
                        catalogExercise.workoutx_name,
                    ].filter(function (part) {
                        return part !== '';
                    }).join(' | ');

                    option.appendChild(title);

                    if (meta.textContent !== '') {
                        option.appendChild(meta);
                    }

                    option.addEventListener('click', function () {
                        selectCatalogExercise(catalogExercise);
                    });

                    exerciseResults.appendChild(option);
                });

                updateHighlightedOption(false);
            }

            async function searchCatalog(term) {
                if (!exerciseResults || !exerciseSearchStatus || String(catalogSearchRoute || '').trim() === '') {
                    return;
                }

                const trimmedTerm = String(term || '').trim();

                if (trimmedTerm.length < 2) {
                    selectedCatalogExercise = null;
                    renderSelectedExercise(null);
                    setExerciseOptions([]);
                    resetCatalogResults();
                    exerciseSearchStatus.textContent = 'Digite pelo menos 2 letras para buscar no catalogo.';
                    return;
                }

                if (catalogSearchAbortController) {
                    catalogSearchAbortController.abort();
                }

                catalogSearchAbortController = new AbortController();
                exerciseSearchStatus.textContent = 'Buscando exercicios...';

                try {
                    const response = await fetch(catalogSearchRoute + '?search=' + encodeURIComponent(trimmedTerm) + '&limit=12', {
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

                    setExerciseOptions(items);
                    exerciseSearchStatus.textContent = items.length > 0
                        ? items.length + ' exercicio(s) encontrado(s).'
                        : 'Nenhum exercicio encontrado para esta busca.';
                } catch (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }

                    setExerciseOptions([]);
                    resetCatalogResults();
                    exerciseSearchStatus.textContent = 'Nao foi possivel buscar o catalogo agora.';
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

                if (exerciseSearchStatus) {
                    exerciseSearchStatus.textContent = 'Digitando...';
                }

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
                        exercise_media_path: '',
                        exercise_media_url: '',
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
                    if (exerciseSearchStatus) {
                        exerciseSearchStatus.textContent = 'Digite pelo menos 2 letras para buscar no catalogo.';
                    }
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
                        if (exerciseSearchStatus) {
                            exerciseSearchStatus.textContent = 'Busca fechada.';
                        }
                    }
                });

                exerciseSearchInput.addEventListener('focus', function () {
                    if (latestCatalogResults.length > 0) {
                        setResultsExpanded(true);
                        updateHighlightedOption(false);
                    }
                });
            }

            document.addEventListener('click', function (event) {
                const pickerRoot = event.target instanceof Node ? event.target.closest('.catalog-picker') : null;
                if (pickerRoot) {
                    return;
                }

                if (exerciseResults) {
                    exerciseResults.innerHTML = '';
                }

                resetCatalogResults();
            });

            renderBoard();
            renderSelectedExercise(null);
            setExerciseOptions([]);
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
