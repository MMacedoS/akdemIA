@php
    $insights = is_array($insights ?? null) ? $insights : [];
    $qualityScores = is_array($insights['quality_scores'] ?? null) ? $insights['quality_scores'] : [];
    $statistics = is_array($insights['statistics'] ?? null) ? $insights['statistics'] : [];
    $references = is_array($insights['references'] ?? null) ? $insights['references'] : [];
    $improvements = is_array($insights['improvements'] ?? null) ? $insights['improvements'] : [];
    $splitLabels = is_array(data_get($insights, 'summary.split_labels')) ? data_get($insights, 'summary.split_labels') : [];
@endphp

@if (($insights['has_content'] ?? false) === true)
    <div class="stats">
        <div class="card">
            <h3>Indicadores do plano</h3>
            <div class="cards-grid" style="margin-top: 12px; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));">
                @forelse ($qualityScores as $score)
                    <div class="mini-card">
                        <strong>{{ (string) ($score['label'] ?? 'Indicador') }}</strong>
                        <small>{{ (int) ($score['value'] ?? 0) }}/100</small>
                    </div>
                @empty
                    <p>Sem indicadores calculados para este treino.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <h3>Estatisticas</h3>
            <div class="stack-list" style="margin-top: 10px;">
                <div class="mini-card"><small>Dias planejados: {{ (int) ($statistics['training_days'] ?? 0) }}</small></div>
                <div class="mini-card"><small>Exercicios especificos: {{ (int) ($statistics['specific_exercises'] ?? 0) }}</small></div>
                <div class="mini-card"><small>Blocos de cardio: {{ (int) ($statistics['cardio_blocks'] ?? 0) }}</small></div>
                @if ($splitLabels !== [])
                    <div class="mini-card"><small>Split: {{ implode(' | ', $splitLabels) }}</small></div>
                @endif
            </div>
        </div>
    </div>

    @if ($references !== [] || $improvements !== [])
        <div class="stats">
            <div class="card">
                <h3>Referencias da geracao</h3>
                <div class="stack-list" style="margin-top: 10px;">
                    @forelse ($references as $reference)
                        <div class="mini-card"><small>{{ (string) $reference }}</small></div>
                    @empty
                        <p>Sem referencias estruturadas para este plano.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <h3>Melhorias aplicadas</h3>
                <div class="stack-list" style="margin-top: 10px;">
                    @forelse ($improvements as $improvement)
                        <div class="mini-card"><small>{{ (string) $improvement }}</small></div>
                    @empty
                        <p>Sem melhorias destacadas para este plano.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
@endif
