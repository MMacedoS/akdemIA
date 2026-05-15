@extends('layouts.panel')

@section('pageTitle', isset($catalog->id) ? 'Editar Catalogo de Treinos' : 'Novo Catalogo de Treinos')
@section('headerTitle', isset($catalog->id) ? 'Editar Catalogo de Treinos' : 'Novo Catalogo de Treinos')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route($routePrefix) }}">Voltar para catalogos</a>
@endsection

@section('content')
    @php
        $isEditing = isset($catalog->id);
        $actionRoute = $isEditing ? route($routePrefix . '.update', $catalog->id) : route($routePrefix . '.store');
        $formId = $isEditing ? 'catalog-update-form' : 'catalog-create-form';
        $resolvedSelectedExerciseIds = collect(old('exercise_media_cache_ids', $selectedExerciseIds))
            ->map(static fn($id) => (int) $id)
            ->filter(static fn($id) => $id > 0)
            ->values()
            ->all();

        $resolvedOrderedExerciseIds = collect(old('exercise_order_ids', $resolvedSelectedExerciseIds))
            ->map(static fn($id) => (int) $id)
            ->filter(static fn($id) => in_array($id, $resolvedSelectedExerciseIds, true))
            ->values()
            ->all();
    @endphp

    <div class="content-stack catalog-screen">
        <div class="card section-title-card">
            <h3>Dados do catalogo</h3>
            <p>Defina nome, descricao e configuracoes. Depois selecione e ordene os exercicios.</p>
        </div>

        <form id="{{ $formId }}" method="POST" action="{{ $actionRoute }}" class="card" style="display: grid; gap: 14px;">
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif

            <div class="field" style="max-width: 100%;">
                <label for="name">Nome do treino</label>
                <input id="name" name="name" type="text" maxlength="60" value="{{ old('name', $catalog->name) }}" required>
            </div>

            <div class="field" style="max-width: 100%;">
                <label for="description">Descricao</label>
                <textarea id="description" name="description" rows="4" maxlength="4000" required>{{ old('description', $catalog->description) }}</textarea>
            </div>

            <div class="stats" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="field" style="max-width: 100%; margin: 0;">
                    <label for="path_image">Caminho da imagem (opcional)</label>
                    <input id="path_image" name="path_image" type="text" maxlength="100" value="{{ old('path_image', $catalog->path_image) }}">
                </div>
            </div>

            <div class="stats" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                <label class="card" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                    <input type="hidden" name="is_public" value="0">
                    <input type="checkbox" name="is_public" value="1" {{ (bool) old('is_public', $catalog->is_public ?? false) ? 'checked' : '' }}>
                    Disponivel para uso publico
                </label>

                <label class="card" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                    <input type="hidden" name="status" value="0">
                    <input type="checkbox" name="status" value="1" {{ (bool) old('status', $catalog->status ?? true) ? 'checked' : '' }}>
                    Catalogo ativo
                </label>
            </div>

            <div class="actions">
                <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
            </div>

            <div id="exercise-hidden-inputs" style="display: none;">
                @foreach ($resolvedOrderedExerciseIds as $orderedExerciseId)
                    <input type="hidden" name="exercise_media_cache_ids[]" value="{{ $orderedExerciseId }}">
                    <input type="hidden" name="exercise_order_ids[]" value="{{ $orderedExerciseId }}">
                @endforeach
            </div>
        </form>

        <div class="card" style="display: grid; gap: 12px;">
            <h3 style="margin: 0;">Exercicios do catalogo</h3>
            <p style="margin: 0;">Selecione os exercicios para o treino e use os filtros sem perder o que ja marcou.</p>

            <form id="exercise-filter-form" class="catalog-filter-grid" onsubmit="return false;">
                <label class="catalog-filter-field">
                    <span>Busca</span>
                    <input type="text" name="exercise_search" value="{{ $exerciseSearch }}" placeholder="Nome, alias ou origem">
                </label>

                <label class="catalog-filter-field">
                    <span>Foco</span>
                    <select name="exercise_focus" class="catalog-filter-select">
                        <option value="">Todos os focos</option>
                        @foreach ($exerciseFocusOptions as $focusOption)
                            <option value="{{ $focusOption }}" {{ $exerciseFocus === $focusOption ? 'selected' : '' }}>{{ ucfirst($focusOption) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="catalog-filter-field">
                    <span>Alvo</span>
                    <select name="exercise_target" class="catalog-filter-select">
                        <option value="">Todos os alvos</option>
                        @foreach ($exerciseTargetOptions as $targetOption)
                            <option value="{{ $targetOption }}" {{ $exerciseTarget === $targetOption ? 'selected' : '' }}>{{ ucfirst($targetOption) }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="catalog-filter-actions">
                    <button id="apply-exercise-filter" class="btn btn-soft" type="button">Filtrar exercicios</button>
                </div>
            </form>

            <div class="catalog-exercises-layout">
                <div class="catalog-exercises-main">
                    <div id="exercise-cards-grid" style="display: grid; gap: 12px; max-height: min(68vh, 760px); overflow: auto; border: 1px solid var(--border); border-radius: 10px; padding: 12px; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
                        @forelse ($exerciseOptions as $exercise)
                            @php
                                $checked = in_array((int) $exercise['id'], $resolvedSelectedExerciseIds, true);
                            @endphp
                            <label
                                class="exercise-card"
                                data-exercise-id="{{ (int) $exercise['id'] }}"
                                data-exercise-name="{{ e($exercise['name']) }}"
                                style="display: grid; gap: 8px; border: 1px solid {{ $checked ? 'var(--primary, #2563eb)' : 'var(--border)' }}; border-radius: 10px; padding: 10px; background: {{ $checked ? 'rgba(37, 99, 235, 0.06)' : '#fff' }};"
                            >
                                <span style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                    <strong style="font-size: 14px; line-height: 1.35;">{{ $exercise['name'] }}</strong>
                                    <input class="exercise-checkbox" type="checkbox" name="exercise_media_cache_ids[]" value="{{ $exercise['id'] }}" form="{{ $formId }}" {{ $checked ? 'checked' : '' }}>
                                </span>

                                @if (! empty($exercise['image_url']))
                                    <img
                                        src="{{ $exercise['image_url'] }}"
                                        alt="Exercicio {{ $exercise['name'] }}"
                                        loading="lazy"
                                        style="width: 100%; aspect-ratio: 4/3; object-fit: cover; border-radius: 8px; border: 1px solid var(--border);"
                                    >
                                @else
                                    <div style="width: 100%; aspect-ratio: 4/3; border-radius: 8px; border: 1px dashed var(--border); display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 12px;">
                                        Sem imagem disponivel
                                    </div>
                                @endif

                                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                    <span style="font-size: 12px; border: 1px solid var(--border); border-radius: 999px; padding: 2px 8px;">Foco: {{ $exercise['focus'] ?: 'geral' }}</span>
                                    @if ($exercise['target'] !== '')
                                        <span style="font-size: 12px; border: 1px solid var(--border); border-radius: 999px; padding: 2px 8px;">Alvo: {{ $exercise['target'] }}</span>
                                    @endif
                                    @if ($exercise['body_part'] !== '')
                                        <span style="font-size: 12px; border: 1px solid var(--border); border-radius: 999px; padding: 2px 8px;">Regiao: {{ $exercise['body_part'] }}</span>
                                    @endif
                                    @if ($exercise['equipment'] !== '')
                                        <span style="font-size: 12px; border: 1px solid var(--border); border-radius: 999px; padding: 2px 8px;">Equip: {{ $exercise['equipment'] }}</span>
                                    @endif
                                </div>
                            </label>
                        @empty
                            <p style="margin: 0; grid-column: 1 / -1;">Nenhum exercicio encontrado com os filtros atuais.</p>
                        @endforelse
                    </div>

                    <small id="exercise-loading-indicator" style="display: none; color: var(--text-muted);">Carregando exercicios...</small>
                </div>

                <aside class="catalog-exercises-side">
                    <div class="card catalog-selected-card" style="display: grid; gap: 8px; margin: 0; padding: 10px;">
                        <strong style="font-size: 14px;">Ordem dos selecionados</strong>
                        <small style="color: var(--text-muted);">Arraste para reordenar. Essa ordem sera salva no treino final.</small>
                        <small id="selected-exercises-count" style="color: var(--text-muted);"></small>
                        <div id="selected-exercises-order-list" style="display: grid; gap: 8px;"></div>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <style>
        .catalog-screen {
            gap: 14px;
        }

        .section-title-card h3 {
            margin: 0;
            font-size: 18px;
        }

        .section-title-card p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .catalog-filter-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            align-items: end;
        }

        .catalog-filter-field {
            display: grid;
            gap: 6px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .catalog-filter-field input,
        .catalog-filter-select {
            border: 1px solid var(--border);
            border-radius: 8px;
            min-height: 40px;
            padding: 9px 10px;
            width: 100%;
            background: #fff;
            color: inherit;
            font: inherit;
        }

        .catalog-filter-select {
            appearance: none;
            background-image: linear-gradient(45deg, transparent 50%, currentColor 50%), linear-gradient(135deg, currentColor 50%, transparent 50%);
            background-position: calc(100% - 16px) calc(50% - 3px), calc(100% - 10px) calc(50% - 3px);
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
            padding-right: 30px;
        }

        .catalog-filter-actions {
            display: flex;
            align-items: end;
        }

        .catalog-filter-actions .btn {
            min-height: 40px;
            width: 100%;
        }

        .catalog-exercises-layout {
            display: grid;
            gap: 12px;
            grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
            align-items: start;
        }

        .catalog-exercises-main {
            min-width: 0;
            display: grid;
            gap: 8px;
        }

        .catalog-exercises-side {
            min-width: 0;
        }

        .catalog-selected-card {
            position: sticky;
            top: 12px;
        }

        #selected-exercises-order-list {
            max-height: min(52vh, 440px);
            overflow: auto;
            padding-right: 4px;
        }

        @media (max-width: 768px) {
            .catalog-filter-actions {
                grid-column: 1 / -1;
            }

            .catalog-exercises-layout {
                grid-template-columns: 1fr;
            }

            .catalog-selected-card {
                position: static;
            }

            #selected-exercises-order-list {
                max-height: 260px;
            }
        }
    </style>

    <script>
        (function () {
            const form = document.getElementById('{{ $formId }}');
            const hiddenInputsContainer = document.getElementById('exercise-hidden-inputs');
            const filterForm = document.getElementById('exercise-filter-form');
            const cardsGrid = document.getElementById('exercise-cards-grid');
            const loadingIndicator = document.getElementById('exercise-loading-indicator');
            const applyFilterButton = document.getElementById('apply-exercise-filter');
            const exerciseOptionsEndpoint = @json($exerciseOptionsEndpoint ?? '');
            const selectedList = document.getElementById('selected-exercises-order-list');
            const selectedCount = document.getElementById('selected-exercises-count');

            if (!form || !hiddenInputsContainer || !selectedList || !cardsGrid) {
                return;
            }

            let currentOrder = Array.from(hiddenInputsContainer.querySelectorAll('input[name="exercise_order_ids[]"]'))
                .map((input) => Number.parseInt(input.value, 10))
                .filter((value) => Number.isInteger(value));

            function getCards() {
                return Array.from(cardsGrid.querySelectorAll('.exercise-card'));
            }

            function getCheckboxes() {
                return Array.from(cardsGrid.querySelectorAll('.exercise-checkbox'));
            }

            function selectedIdsInGridOrder() {
                return getCheckboxes()
                    .filter((checkbox) => checkbox.checked)
                    .map((checkbox) => Number.parseInt(checkbox.value, 10))
                    .filter((value) => Number.isInteger(value));
            }

            function syncOrderWithSelection() {
                const selectedIds = selectedIdsInGridOrder();
                const selectedSet = new Set(selectedIds);
                const kept = currentOrder.filter((id) => selectedSet.has(id));
                const missing = selectedIds.filter((id) => !kept.includes(id));
                currentOrder = [...kept, ...missing];
            }

            function cardMetaById(exerciseId) {
                const card = getCards().find((item) => Number.parseInt(item.dataset.exerciseId, 10) === exerciseId);

                return {
                    id: exerciseId,
                    name: card?.dataset.exerciseName || ('Exercicio #' + exerciseId),
                };
            }

            function renderHiddenInputs() {
                hiddenInputsContainer.innerHTML = '';

                currentOrder.forEach((exerciseId) => {
                    const selectedInput = document.createElement('input');
                    selectedInput.type = 'hidden';
                    selectedInput.name = 'exercise_media_cache_ids[]';
                    selectedInput.value = String(exerciseId);
                    hiddenInputsContainer.appendChild(selectedInput);

                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'exercise_order_ids[]';
                    input.value = String(exerciseId);
                    hiddenInputsContainer.appendChild(input);
                });
            }

            function moveItem(oldIndex, newIndex) {
                if (oldIndex < 0 || newIndex < 0 || oldIndex === newIndex) {
                    return;
                }

                const nextOrder = [...currentOrder];
                const [moved] = nextOrder.splice(oldIndex, 1);
                nextOrder.splice(newIndex, 0, moved);
                currentOrder = nextOrder;
            }

            function renderSelectedList() {
                selectedList.innerHTML = '';

                if (currentOrder.length === 0) {
                    if (selectedCount) {
                        selectedCount.textContent = 'Selecionados: 0';
                    }

                    const empty = document.createElement('div');
                    empty.textContent = 'Nenhum exercicio selecionado.';
                    empty.style.cssText = 'font-size:12px;color:var(--text-muted);padding:4px 0;';
                    selectedList.appendChild(empty);
                    return;
                }

                if (selectedCount) {
                    selectedCount.textContent = 'Selecionados: ' + currentOrder.length;
                }

                currentOrder.forEach((exerciseId, index) => {
                    const meta = cardMetaById(exerciseId);
                    const item = document.createElement('div');
                    item.draggable = true;
                    item.dataset.orderIndex = String(index);
                    item.style.cssText = 'display:flex;align-items:center;gap:8px;border:1px dashed var(--border);border-radius:8px;padding:8px;background:#fff;cursor:grab;';
                    item.innerHTML = '<span style="font-size:12px;opacity:.7;">↕</span><strong style="font-size:13px;">' + (index + 1) + '.</strong><span style="font-size:13px;">' + meta.name + '</span>';

                    item.addEventListener('dragstart', function (event) {
                        event.dataTransfer?.setData('text/plain', String(index));
                        event.dataTransfer.effectAllowed = 'move';
                    });

                    item.addEventListener('dragover', function (event) {
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                    });

                    item.addEventListener('drop', function (event) {
                        event.preventDefault();
                        const fromIndex = Number.parseInt(event.dataTransfer?.getData('text/plain') || '', 10);
                        const toIndex = Number.parseInt(item.dataset.orderIndex || '', 10);

                        if (!Number.isInteger(fromIndex) || !Number.isInteger(toIndex)) {
                            return;
                        }

                        moveItem(fromIndex, toIndex);
                        renderAll();
                    });

                    selectedList.appendChild(item);
                });
            }

            function updateCardsState() {
                getCards().forEach((card) => {
                    const checkbox = card.querySelector('.exercise-checkbox');
                    const exerciseId = Number.parseInt(checkbox?.value || '', 10);
                    const checked = Number.isInteger(exerciseId) && currentOrder.includes(exerciseId);

                    if (checkbox) {
                        checkbox.checked = checked;
                    }

                    card.style.border = checked ? '1px solid var(--primary, #2563eb)' : '1px solid var(--border)';
                    card.style.background = checked ? 'rgba(37, 99, 235, 0.06)' : '#fff';
                });
            }

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function renderExerciseCards(exercises) {
                if (!Array.isArray(exercises) || exercises.length === 0) {
                    cardsGrid.innerHTML = '<p style="margin: 0; grid-column: 1 / -1;">Nenhum exercicio encontrado com os filtros atuais.</p>';
                    bindCheckboxes();
                    updateCardsState();
                    return;
                }

                cardsGrid.innerHTML = exercises.map((exercise) => {
                    const id = Number.parseInt(String(exercise.id || ''), 10);
                    const isChecked = Number.isInteger(id) && currentOrder.includes(id);
                    const name = escapeHtml(exercise.name || 'Exercicio sem nome');
                    const focus = escapeHtml(exercise.focus || 'geral');
                    const target = escapeHtml(exercise.target || '');
                    const bodyPart = escapeHtml(exercise.body_part || '');
                    const equipment = escapeHtml(exercise.equipment || '');
                    const imageUrl = typeof exercise.image_url === 'string' ? exercise.image_url : '';
                    const imageHtml = imageUrl !== ''
                        ? '<img src="' + escapeHtml(imageUrl) + '" alt="Exercicio ' + name + '" loading="lazy" style="width: 100%; aspect-ratio: 4/3; object-fit: cover; border-radius: 8px; border: 1px solid var(--border);">'
                        : '<div style="width: 100%; aspect-ratio: 4/3; border-radius: 8px; border: 1px dashed var(--border); display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 12px;">Sem imagem disponivel</div>';

                    return '<label class="exercise-card" data-exercise-id="' + id + '" data-exercise-name="' + name + '" style="display: grid; gap: 8px; border: 1px solid ' + (isChecked ? 'var(--primary, #2563eb)' : 'var(--border)') + '; border-radius: 10px; padding: 10px; background: ' + (isChecked ? 'rgba(37, 99, 235, 0.06)' : '#fff') + ';">'
                        + '<span style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">'
                        + '<strong style="font-size: 14px; line-height: 1.35;">' + name + '</strong>'
                        + '<input class="exercise-checkbox" type="checkbox" value="' + id + '" ' + (isChecked ? 'checked' : '') + '>'
                        + '</span>'
                        + imageHtml
                        + '<div style="display: flex; flex-wrap: wrap; gap: 6px;">'
                        + '<span style="font-size: 12px; border: 1px solid var(--border); border-radius: 999px; padding: 2px 8px;">Foco: ' + focus + '</span>'
                        + (target !== '' ? '<span style="font-size: 12px; border: 1px solid var(--border); border-radius: 999px; padding: 2px 8px;">Alvo: ' + target + '</span>' : '')
                        + (bodyPart !== '' ? '<span style="font-size: 12px; border: 1px solid var(--border); border-radius: 999px; padding: 2px 8px;">Regiao: ' + bodyPart + '</span>' : '')
                        + (equipment !== '' ? '<span style="font-size: 12px; border: 1px solid var(--border); border-radius: 999px; padding: 2px 8px;">Equip: ' + equipment + '</span>' : '')
                        + '</div>'
                        + '</label>';
                }).join('');

                bindCheckboxes();
                updateCardsState();
            }

            function updateFilterSelect(selectName, options, currentValue) {
                const select = filterForm?.querySelector('select[name="' + selectName + '"]');

                if (!select) {
                    return;
                }

                const defaultLabel = selectName === 'exercise_focus' ? 'Todos os focos' : 'Todos os alvos';
                const normalizedOptions = Array.isArray(options) ? options : [];
                const escapedCurrentValue = String(currentValue || '');

                select.innerHTML = '<option value="">' + defaultLabel + '</option>'
                    + normalizedOptions.map((option) => {
                        const label = String(option);
                        const selected = escapedCurrentValue === label ? ' selected' : '';
                        return '<option value="' + escapeHtml(label) + '"' + selected + '>' + escapeHtml(label.charAt(0).toUpperCase() + label.slice(1)) + '</option>';
                    }).join('');

                if (escapedCurrentValue !== '' && !normalizedOptions.includes(escapedCurrentValue)) {
                    const customOption = document.createElement('option');
                    customOption.value = escapedCurrentValue;
                    customOption.textContent = escapedCurrentValue.charAt(0).toUpperCase() + escapedCurrentValue.slice(1);
                    customOption.selected = true;
                    select.appendChild(customOption);
                }
            }

            async function fetchExerciseOptions() {
                if (!filterForm || exerciseOptionsEndpoint === '') {
                    return;
                }

                const searchInput = filterForm.querySelector('input[name="exercise_search"]');
                const focusSelect = filterForm.querySelector('select[name="exercise_focus"]');
                const targetSelect = filterForm.querySelector('select[name="exercise_target"]');
                const params = new URLSearchParams();

                if (searchInput?.value?.trim()) {
                    params.set('exercise_search', searchInput.value.trim());
                }

                if (focusSelect?.value?.trim()) {
                    params.set('exercise_focus', focusSelect.value.trim());
                }

                if (targetSelect?.value?.trim()) {
                    params.set('exercise_target', targetSelect.value.trim());
                }

                currentOrder.forEach((exerciseId) => {
                    params.append('selected_ids[]', String(exerciseId));
                });

                if (loadingIndicator) {
                    loadingIndicator.style.display = 'block';
                }

                if (applyFilterButton) {
                    applyFilterButton.disabled = true;
                }

                try {
                    const response = await fetch(exerciseOptionsEndpoint + '?' + params.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        throw new Error('Falha ao buscar exercicios.');
                    }

                    const payload = await response.json();
                    renderExerciseCards(payload.exerciseOptions || []);
                    updateFilterSelect('exercise_focus', payload.exerciseFocusOptions || [], focusSelect?.value || '');
                    updateFilterSelect('exercise_target', payload.exerciseTargetOptions || [], targetSelect?.value || '');
                } catch (error) {
                    console.error(error);
                } finally {
                    if (loadingIndicator) {
                        loadingIndicator.style.display = 'none';
                    }

                    if (applyFilterButton) {
                        applyFilterButton.disabled = false;
                    }

                    renderAll();
                }
            }

            function bindCheckboxes() {
                getCheckboxes().forEach((checkbox) => {
                    checkbox.addEventListener('change', function () {
                        const exerciseId = Number.parseInt(checkbox.value, 10);

                        if (!Number.isInteger(exerciseId)) {
                            return;
                        }

                        if (checkbox.checked) {
                            if (!currentOrder.includes(exerciseId)) {
                                currentOrder.push(exerciseId);
                            }
                        } else {
                            currentOrder = currentOrder.filter((id) => id !== exerciseId);
                        }

                        renderAll();
                    });
                });
            }

            function renderAll() {
                syncOrderWithSelection();
                renderHiddenInputs();
                renderSelectedList();
                updateCardsState();
            }

            if (filterForm) {
                filterForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    fetchExerciseOptions();
                });
            }

            if (applyFilterButton) {
                applyFilterButton.addEventListener('click', function () {
                    fetchExerciseOptions();
                });
            }

            bindCheckboxes();

            renderAll();
        })();
    </script>
@endsection
