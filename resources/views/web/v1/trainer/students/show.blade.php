@extends('layouts.panel')

@section('pageTitle', 'Perfil do Aluno')
@section('headerTitle', 'Visao do Trainer')

@section('headerAction')
    <div class="actions">
        <button id="open-generate-modal-btn" class="btn btn-primary" type="button">Gerar treino</button>
        <a class="btn btn-soft" href="{{ route('trainer.students.edit', $student->id) }}">Editar saude</a>
    </div>
@endsection

@section('content')
    @php
        $generatedWorkouts = $workouts->filter(static function ($workout) {
            return is_array($workout->workout_plan) && ! empty($workout->workout_plan);
        });
        $otherWorkouts = $workouts->reject(static function ($workout) {
            return is_array($workout->workout_plan) && ! empty($workout->workout_plan);
        });
        $generatedCount = $generatedWorkouts->count();
        $activeGeneratedCount = $generatedWorkouts->filter(static function ($workout) {
            return (string) ($workout->request_status ?? 'active') === 'active';
        })->count();
    @endphp

    <div class="content-stack trainer-student-show">
        <section class="card student-hero">
            <div>
                <h3>{{ $student->name }}</h3>
                <p>{{ $student->email }}</p>
                <p style="margin-top: 10px;">Objetivo: {{ $student->goal ?: 'Nao informado' }}</p>
            </div>

            <div class="hero-metrics">
                <article class="hero-metric">
                    <small>Treinos (10 ultimos)</small>
                    <strong>{{ $workouts->count() }}</strong>
                </article>
                <article class="hero-metric">
                    <small>Treinos gerados</small>
                    <strong>{{ $generatedCount }}</strong>
                </article>
                <article class="hero-metric">
                    <small>Gerados ativos</small>
                    <strong>{{ $activeGeneratedCount }}</strong>
                </article>
            </div>
        </section>

        @if (($availableCatalogsToApply ?? collect())->isNotEmpty())
            <section class="card compact-section">
                <form
                    method="POST"
                    action="{{ route('trainer.students.workouts.catalog.apply', [$student->id, 0]) }}"
                    data-action-base="{{ route('trainer.students.workouts.catalog.apply', [$student->id, 0]) }}"
                    class="catalog-filter-form"
                    id="catalog-apply-form"
                >
                    @csrf
                    <label for="catalog-apply-input">Aplicar catalogo pronto no aluno</label>
                    <div class="catalog-filter-row">
                        <select id="catalog-apply-input" name="catalog_id" required>
                            <option value="">Selecione um catalogo</option>
                            @foreach ($availableCatalogsToApply as $catalogOption)
                                <option value="{{ (int) $catalogOption['id'] }}">{{ $catalogOption['name'] }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary">Aplicar catalogo</button>
                    </div>
                </form>
            </section>
        @endif

        @if (($availableAppliedCatalogs ?? collect())->isNotEmpty())
            <section class="card compact-section">
                <form method="GET" action="{{ route('trainer.students.show', $student->id) }}" class="catalog-filter-form">
                    <label for="catalog-filter-input">Filtrar por catalogo aplicado</label>
                    <div class="catalog-filter-row">
                        <select id="catalog-filter-input" name="catalog_id">
                            <option value="">Todos os catalogos</option>
                            @foreach ($availableAppliedCatalogs as $catalogOption)
                                <option value="{{ $catalogOption['id'] }}" @selected((int) ($selectedCatalogId ?? 0) === (int) $catalogOption['id'])>{{ $catalogOption['name'] }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-soft">Aplicar filtro</button>
                        @if (($selectedCatalogId ?? null) !== null)
                            <a href="{{ route('trainer.students.show', $student->id) }}" class="btn btn-soft">Limpar</a>
                        @endif
                    </div>
                </form>
            </section>
        @endif

        @if (($latestWorkoutInsights['has_content'] ?? false) === true)
            <section class="card compact-section">
                <div class="section-head">
                    <h3>Resumo do ultimo treino</h3>
                    <span class="section-note">Baseado no treino #{{ $latestWorkout?->id }}</span>
                </div>

                @include('web.v1.workouts._insights', ['insights' => $latestWorkoutInsights])
            </section>
        @endif

        <section class="card compact-section">
            <div class="section-head">
                <h3>Panorama do aluno</h3>
                <span class="section-note">Visao rapida de saude e preferencias</span>
            </div>

            <div class="quick-grid">
                <article class="quick-panel">
                    <h4>Dados fisicos</h4>
                    <ul class="kv-list">
                        <li><span>Altura</span><strong>{{ $student->height ?? 'Nao informado' }}</strong></li>
                        <li><span>Peso</span><strong>{{ $student->weight ?? 'Nao informado' }}</strong></li>
                        <li><span>% Gordura</span><strong>{{ optional($student->physicalData)->body_fat_percentage ?? 'Nao informado' }}</strong></li>
                        <li><span>IMC</span><strong>{{ optional($student->physicalData)->imc ?? 'Nao informado' }}</strong></li>
                    </ul>
                </article>

                <article class="quick-panel">
                    <h4>Dados medicos</h4>
                    <ul class="kv-list">
                        <li><span>Lesoes</span><strong>{{ optional($student->medicalData)->injuries ?? 'Nao informado' }}</strong></li>
                        <li><span>Doencas</span><strong>{{ optional($student->medicalData)->diseases ?? 'Nao informado' }}</strong></li>
                        <li><span>Medicacoes</span><strong>{{ optional($student->medicalData)->medications ?? 'Nao informado' }}</strong></li>
                        <li><span>Restricoes</span><strong>{{ optional($student->medicalData)->restrictions ?? 'Nao informado' }}</strong></li>
                    </ul>
                </article>

                <article class="quick-panel">
                    <h4>Preferencias</h4>
                    <ul class="kv-list">
                        <li><span>Alimentos preferidos</span><strong>{{ is_array(optional($student->preference)->preferred_foods) ? implode(', ', $student->preference->preferred_foods) : 'Nao informado' }}</strong></li>
                        <li><span>Alimentos evitados</span><strong>{{ is_array(optional($student->preference)->disliked_foods) ? implode(', ', $student->preference->disliked_foods) : 'Nao informado' }}</strong></li>
                        <li><span>Bebidas</span><strong>{{ is_array(optional($student->preference)->drinks) ? implode(', ', $student->preference->drinks) : 'Nao informado' }}</strong></li>
                        <li><span>Frequencia</span><strong>{{ optional($student->preference)->training_frequency ?? 'Nao informado' }}</strong></li>
                    </ul>
                </article>
            </div>
        </section>

        <section class="card compact-section">
            <div class="section-head">
                <h3>Treinos gerados</h3>
                <span class="status-badge success">{{ $generatedCount }} encontrados</span>
            </div>

            @if ($generatedCount > 0)
                <div class="workout-grid generated-grid">
                    @foreach ($generatedWorkouts as $index => $workout)
                        @php
                            $requestStatus = (string) ($workout->request_status ?? 'active');
                            $status = (string) $workout->status;
                        @endphp
                        <article class="workout-item generated {{ $requestStatus === 'active' ? 'is-active' : 'is-inactive' }}">
                            <div class="workout-item-head">
                                <h4>Treino #{{ $workout->id }}</h4>
                                <div class="workout-badges">
                                    @if ($index === 0)
                                        <span class="status-badge highlight">Mais recente</span>
                                    @endif
                                    <span class="status-badge {{ $requestStatus === 'active' ? 'success' : 'neutral' }}">{{ strtoupper($requestStatus) }}</span>
                                </div>
                            </div>
                            <p class="workout-meta">Processamento: {{ strtoupper($status) }}</p>
                            @if ((int) ($workout->source_workout_catalog_id ?? 0) > 0)
                                <p class="workout-meta">Catalogo: {{ $workout->source_workout_catalog_name ?: ('#' . (int) $workout->source_workout_catalog_id) }}</p>
                            @endif
                            <p class="workout-meta">Criado em {{ optional($workout->created_at)?->format('d/m/Y H:i') }}</p>
                            <div class="actions">
                                <a class="btn btn-primary" href="{{ route('trainer.students.workouts.show', [$student->id, $workout->id]) }}">Abrir board</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <p>Nenhum treino gerado com conteudo disponivel para este aluno.</p>
                </div>
            @endif
        </section>

        @if ($otherWorkouts->isNotEmpty())
            <section class="card compact-section">
                <div class="section-head">
                    <h3>Outros registros</h3>
                    <span class="section-note">Treinos sem conteudo final ou em transicao</span>
                </div>

                <div class="workout-grid">
                    @foreach ($otherWorkouts as $workout)
                        @php
                            $requestStatus = (string) ($workout->request_status ?? 'active');
                        @endphp
                        <article class="workout-item">
                            <div class="workout-item-head">
                                <h4>Treino #{{ $workout->id }}</h4>
                                <span class="status-badge {{ $requestStatus === 'active' ? 'success' : 'neutral' }}">{{ strtoupper($requestStatus) }}</span>
                            </div>
                            <p class="workout-meta">Status: {{ strtoupper((string) $workout->status) }}</p>
                            @if ((int) ($workout->source_workout_catalog_id ?? 0) > 0)
                                <p class="workout-meta">Catalogo: {{ $workout->source_workout_catalog_name ?: ('#' . (int) $workout->source_workout_catalog_id) }}</p>
                            @endif
                            <p class="workout-meta">Criado em {{ optional($workout->created_at)?->format('d/m/Y H:i') }}</p>
                            <div class="actions">
                                <a class="btn btn-soft" href="{{ route('trainer.students.workouts.show', [$student->id, $workout->id]) }}">Visualizar board</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    <div id="generate-workout-modal" class="plan-refazer-modal" aria-hidden="true">
        <div class="plan-refazer-modal-card">
            <div class="plan-refazer-modal-head">
                <h4>Gerar treino + dieta</h4>
                <button id="close-generate-modal-btn" type="button" class="btn btn-soft">Fechar</button>
            </div>

            <form method="POST" action="{{ route('trainer.students.workouts.generate', $student->id) }}" class="content-stack">
                @csrf
                <div class="field" style="max-width: 100%;">
                    <label for="generate_adjustment_request">Observacoes para IA (opcional)</label>
                    <textarea id="generate_adjustment_request" name="adjustment_request" rows="3" maxlength="1500" placeholder="Ex: priorizar treino com baixo impacto no joelho.">{{ old('adjustment_request') }}</textarea>
                </div>
                <div class="actions">
                    <button class="btn btn-primary" type="submit">Iniciar geracao</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .trainer-student-show {
            gap: 16px;
        }

        .trainer-student-show .student-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(280px, 1fr);
            gap: 14px;
            align-items: start;
            background: linear-gradient(140deg, #ffffff 0%, #f6fbff 60%, #edf7ff 100%);
        }

        .trainer-student-show .student-hero h3 {
            margin-bottom: 6px;
            font-size: 21px;
        }

        .hero-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .hero-metric {
            border: 1px solid #d5e7f7;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.92);
            padding: 10px;
            display: grid;
            gap: 4px;
        }

        .hero-metric small {
            font-size: 11px;
            color: #587089;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .hero-metric strong {
            font-size: 24px;
            line-height: 1;
            color: #0b5ea8;
        }

        .compact-section {
            display: grid;
            gap: 12px;
        }

        .catalog-filter-form {
            display: grid;
            gap: 8px;
        }

        .catalog-filter-form label {
            font-size: 13px;
            color: #4f6478;
            font-weight: 600;
        }

        .catalog-filter-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .catalog-filter-row select {
            min-width: 260px;
            min-height: 42px;
            padding: 0 40px 0 12px;
            border: 1px solid #c8d9ea;
            border-radius: 12px;
            background-color: #ffffff;
            background-image: linear-gradient(180deg, #ffffff 0%, #f4f9ff 100%);
            color: #163a5c;
            font-weight: 600;
            box-shadow: 0 8px 18px rgba(19, 66, 109, 0.08);
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 10px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%23627c97' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"), linear-gradient(180deg, #ffffff 0%, #f4f9ff 100%);
        }

        .catalog-filter-row select:hover {
            border-color: #9fbce0;
        }

        .catalog-filter-row select:focus {
            outline: none;
            border-color: #4f8fce;
            box-shadow: 0 0 0 3px rgba(79, 143, 206, 0.16), 0 10px 22px rgba(19, 66, 109, 0.12);
            transform: translateY(-1px);
        }

        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
        }

        .section-head h3 {
            margin: 0;
        }

        .section-note {
            color: #6f7f91;
            font-size: 13px;
        }

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .quick-panel {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            background: #ffffff;
        }

        .quick-panel h4 {
            margin: 0 0 10px;
            font-size: 14px;
            color: #3d5369;
        }

        .kv-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 8px;
        }

        .kv-list li {
            display: grid;
            gap: 4px;
        }

        .kv-list span {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #7990a7;
        }

        .kv-list strong {
            font-size: 13px;
            color: #243648;
            word-break: break-word;
        }

        .workout-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .generated-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .workout-item {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            background: #fff;
            display: grid;
            gap: 10px;
        }

        .workout-item.generated {
            border-color: #8ec0f0;
            background: linear-gradient(145deg, #ffffff 0%, #f3faff 100%);
            box-shadow: 0 8px 20px rgba(27, 95, 163, 0.09);
        }

        .workout-item.generated.is-active {
            border-left: 4px solid #2b7bc3;
        }

        .workout-item.generated.is-inactive {
            border-left: 4px solid #8f9eae;
        }

        .workout-item-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
        }

        .workout-item-head h4 {
            margin: 0;
            font-size: 15px;
            color: #1b3551;
        }

        .workout-badges {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 6px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
            border: 1px solid transparent;
            text-transform: uppercase;
        }

        .status-badge.success {
            color: #0d5f2f;
            background: #e7f8ee;
            border-color: #b5e8c9;
        }

        .status-badge.neutral {
            color: #445567;
            background: #eef2f6;
            border-color: #d2dbe4;
        }

        .status-badge.highlight {
            color: #0b4a83;
            background: #dff0ff;
            border-color: #9ac9ef;
        }

        .workout-meta {
            margin: 0;
            color: #5f7183;
            font-size: 13px;
        }

        .empty-state {
            border: 1px dashed #b8c9db;
            border-radius: 12px;
            padding: 14px;
            background: #f8fbff;
        }

        .empty-state p {
            margin: 0;
            color: #62778d;
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

        @media (max-width: 1024px) {
            .trainer-student-show .student-hero {
                grid-template-columns: 1fr;
            }

            .hero-metrics,
            .quick-grid,
            .workout-grid,
            .generated-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        (function () {
            const catalogApplyForm = document.getElementById('catalog-apply-form');
            const catalogApplyInput = document.getElementById('catalog-apply-input');

            if (catalogApplyForm && catalogApplyInput) {
                catalogApplyForm.addEventListener('submit', function (event) {
                    const catalogId = String(catalogApplyInput.value || '').trim();
                        const selectedOption = catalogApplyInput.options[catalogApplyInput.selectedIndex] || null;
                        const selectedCatalogLabel = selectedOption ? String(selectedOption.textContent || '').trim() : '';

                    if (catalogId === '') {
                        event.preventDefault();
                        return;
                    }

                        const shouldApply = window.confirm(
                            'Aplicar o catalogo "' + (selectedCatalogLabel || 'selecionado') + '" para este aluno?\n\n' +
                            'Isso cria um novo treino ativo para o aluno.'
                        );

                        if (!shouldApply) {
                            event.preventDefault();
                            return;
                        }

                    const actionBase = catalogApplyForm.getAttribute('data-action-base') || '';
                    catalogApplyForm.setAttribute('action', actionBase.replace(/\/0\/apply$/, '/' + catalogId + '/apply'));
                });
            }

            const openButton = document.getElementById('open-generate-modal-btn');
            const closeButton = document.getElementById('close-generate-modal-btn');
            const modal = document.getElementById('generate-workout-modal');

            function closeModal() {
                if (!modal) {
                    return;
                }

                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            }

            if (openButton && modal) {
                openButton.addEventListener('click', function () {
                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                });
            }

            if (closeButton) {
                closeButton.addEventListener('click', closeModal);
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
@endsection
