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

    .exercise-card-thumb {
        margin-top: 8px;
    }

    .exercise-card-thumb .exercise-preview-figure {
        padding: 6px;
    }

    .exercise-card-thumb img,
    .exercise-card-thumb svg {
        max-height: 120px;
        object-fit: contain;
    }

    .exercise-image-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 140;
        background: rgba(15, 23, 42, 0.58);
        align-items: center;
        justify-content: center;
        padding: 16px;
    }

    .exercise-image-modal.open {
        display: flex;
    }

    .exercise-image-modal-card {
        width: min(760px, 100%);
        max-height: calc(100vh - 32px);
        overflow: auto;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: var(--shadow);
        padding: 16px;
        display: grid;
        gap: 12px;
    }

    .exercise-image-modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .exercise-image-modal-head h4 {
        margin: 0;
        font-size: 18px;
    }

    .exercise-image-modal-figure {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #f8fafc;
        padding: 12px;
    }

    .exercise-image-modal-figure img,
    .exercise-image-modal-figure svg {
        width: 100%;
        height: auto;
        display: block;
        max-height: 65vh;
        object-fit: contain;
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
                    <input id="exercise-name-input" type="text" placeholder="Nome do exercicio">
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

<div id="exercise-image-modal" class="exercise-image-modal" aria-hidden="true">
    <div class="exercise-image-modal-card">
        <div class="exercise-image-modal-head">
            <div>
                <h4 id="exercise-image-modal-title">Imagem do exercicio</h4>
                <p id="exercise-image-modal-subtitle" style="margin: 4px 0 0; color: var(--muted);">Visualizacao ampliada da midia do exercicio.</p>
            </div>
            <button id="exercise-image-modal-close" type="button" class="btn btn-soft">Fechar</button>
        </div>
        <div id="exercise-image-modal-figure" class="exercise-image-modal-figure"></div>
    </div>
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
        const exerciseImageModal = document.getElementById('exercise-image-modal');
        const exerciseImageModalClose = document.getElementById('exercise-image-modal-close');
        const exerciseImageModalFigure = document.getElementById('exercise-image-modal-figure');
        const exerciseImageModalTitle = document.getElementById('exercise-image-modal-title');
        const exerciseImageModalSubtitle = document.getElementById('exercise-image-modal-subtitle');

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

        function closeExerciseImageModal() {
            if (!exerciseImageModal) {
                return;
            }

            exerciseImageModal.classList.remove('open');
            exerciseImageModal.setAttribute('aria-hidden', 'true');
            if (exerciseImageModalFigure) {
                exerciseImageModalFigure.innerHTML = '';
            }
        }

        function renderExerciseMedia(mediaUrl, svgMarkup) {
            if (String(mediaUrl || '').trim() !== '') {
                return '<img src="' + String(mediaUrl).replace(/"/g, '&quot;') + '" alt="Midia do exercicio" loading="lazy">';
            }

            return String(svgMarkup || '');
        }

        function openExerciseImageModal(title, subtitle, mediaUrl, svgMarkup) {
            if (!exerciseImageModal || !exerciseImageModalFigure) {
                return;
            }

            exerciseImageModalTitle.textContent = title || 'Imagem do exercicio';
            exerciseImageModalSubtitle.textContent = subtitle || 'Visualizacao ampliada da midia do exercicio.';
            exerciseImageModalFigure.innerHTML = renderExerciseMedia(mediaUrl, svgMarkup);
            exerciseImageModal.classList.add('open');
            exerciseImageModal.setAttribute('aria-hidden', 'false');
        }

        function bindExercisePreviewButtons(root) {
            if (!root) {
                return;
            }

            root.querySelectorAll('[data-exercise-preview-button]').forEach(function (button) {
                button.addEventListener('click', function () {
                    openExerciseImageModal(
                        button.getAttribute('data-exercise-name') || 'Imagem do exercicio',
                        button.getAttribute('data-exercise-focus') || 'Visualizacao ampliada da ilustracao do exercicio.',
                        button.getAttribute('data-exercise-media-url') || '',
                        button.getAttribute('data-exercise-svg') || ''
                    );
                });
            });
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

        if (exerciseImageModalClose) {
            exerciseImageModalClose.addEventListener('click', closeExerciseImageModal);
        }

        if (exerciseImageModal) {
            exerciseImageModal.addEventListener('click', function (event) {
                if (event.target === exerciseImageModal) {
                    closeExerciseImageModal();
                }
            });
        }

        bindExercisePreviewButtons(document);

        window.akdemiaOpenExerciseImageModal = openExerciseImageModal;
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

            const columnsContainer = document.getElementById('manual-board-columns');
            const daySelect = document.getElementById('exercise-day-select');
            const hiddenInput = document.getElementById('manual-weekly-plan-input');

            const newDayNameInput = document.getElementById('new-day-name');
            const newDayFocusInput = document.getElementById('new-day-focus');
            const addDayButton = document.getElementById('add-day-btn');

            const exerciseNameInput = document.getElementById('exercise-name-input');
            const exerciseSetsInput = document.getElementById('exercise-sets-input');
            const exerciseRepsInput = document.getElementById('exercise-reps-input');
            const exerciseRestInput = document.getElementById('exercise-rest-input');
            const exerciseCategoryInput = document.getElementById('exercise-category-input');
            const addExerciseButton = document.getElementById('add-exercise-btn');

            const boardForm = document.getElementById('manual-board-form');

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

                        const hasVisual = safeExercise.exercise_media_url || safeExercise.illustration_svg;

                        if (hasVisual) {
                            const preview = document.createElement('div');
                            preview.className = 'exercise-card-thumb';

                            const figure = document.createElement('div');
                            figure.className = 'exercise-preview-figure';
                            figure.innerHTML = renderExerciseMedia(safeExercise.exercise_media_url, safeExercise.illustration_svg);
                            preview.appendChild(figure);
                            card.appendChild(preview);

                            const actions = document.createElement('div');
                            actions.className = 'exercise-card-actions';

                            const previewButton = document.createElement('button');
                            previewButton.type = 'button';
                            previewButton.className = 'btn btn-soft';
                            previewButton.textContent = 'Ver imagem';
                            previewButton.addEventListener('click', function () {
                                if (typeof window.akdemiaOpenExerciseImageModal === 'function') {
                                    window.akdemiaOpenExerciseImageModal(
                                        safeExercise.name,
                                        String(dayPlan.focus || 'Treino geral'),
                                        safeExercise.exercise_media_url,
                                        safeExercise.illustration_svg
                                    );
                                }
                            });

                            actions.appendChild(previewButton);
                            card.appendChild(actions);
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

                    const name = String(exerciseNameInput.value || '').trim();
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
                        workoutx_name: '',
                        exercise_media_path: '',
                        exercise_media_url: '',
                        illustration_svg: '',
                    });

                    weeklyPlan[dayIndex].exercises.push(newExercise);

                    exerciseNameInput.value = '';
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

            renderBoard();
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

                                        <div class="exercise-card-actions">
                                            <button
                                                type="button"
                                                class="btn btn-soft"
                                                data-exercise-preview-button
                                                data-exercise-name="{{ (string) data_get($exercise, 'name', 'Exercicio') }}"
                                                data-exercise-focus="{{ (string) data_get($dayPlan, 'focus', 'Treino geral') }}"
                                                data-exercise-media-url="{{ $exerciseMediaUrl }}"
                                                data-exercise-svg="{{ $exerciseSvg }}"
                                            >Ver imagem</button>
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
