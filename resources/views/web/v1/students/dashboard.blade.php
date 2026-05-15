@extends('layouts.panel')

@section('pageTitle', 'Dashboard do Aluno')
@section('headerTitle', 'Painel do Aluno')

@section('headerAction')
    <a class="btn btn-primary" href="{{ route('students.health.edit') }}">Atualizar saude</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="stats">
            <div class="card">
                <h3>Perfil preenchido</h3>
                <p class="stat-value">{{ $summary['profile_completion'] }}/4</p>
                <span class="badge primary">Dados essenciais</span>
            </div>

            <div class="card">
                <h3>Status do ultimo treino</h3>
                <p class="stat-value" style="font-size: 22px;">{{ strtoupper($summary['latest_workout_status']) }}</p>
                <span class="badge warning">Plano atual</span>
            </div>

            <div class="card">
                <h3>Gerado em</h3>
                <p class="stat-value" style="font-size: 18px;">{{ $summary['latest_workout_date'] ?? 'Nao gerado' }}</p>
                <span class="badge success">Ultima atualizacao</span>
            </div>
        </div>

        <div class="card">
            <h3>Funcoes e atribuicoes</h3>
            <p>Como aluno, voce pode manter seus dados de saude atualizados, acompanhar seu treino e ajustar seu perfil para gerar recomendacoes melhores.</p>
            <p>Trainer responsavel: {{ $assignedTrainee?->name ?? 'Nao informado' }}</p>
            @if ($assignedTrainee)
                <p>Contato do trainer: {{ $assignedTrainee->email }}</p>
            @endif
        </div>

        <div class="actions">
            @if ($summary['has_tenant'])
                <a class="btn btn-primary" href="{{ route('students.workout.show') }}">Ver meu treino</a>
            @endif
            <a class="btn btn-soft" href="{{ route('students.health.edit') }}">Editar dados de saude</a>
            <a class="btn btn-soft" href="{{ route('profile.edit') }}">Editar perfil</a>
        </div>

        @if (($workoutStatistics['has_content'] ?? false) === true)
            <div class="card">
                <h3>Tendencia dos ultimos treinos</h3>
                <p>Resumo agregado dos {{ (int) ($workoutStatistics['recent_workouts'] ?? 0) }} treinos concluidos mais recentes.</p>
            </div>

            <div class="stats">
                <div class="card">
                    <h3>Dias planejados</h3>
                    <p class="stat-value">{{ (int) ($workoutStatistics['training_days_total'] ?? 0) }}</p>
                    <span class="badge primary">Historico recente</span>
                </div>

                <div class="card">
                    <h3>Exercicios especificos</h3>
                    <p class="stat-value">{{ (int) ($workoutStatistics['specific_exercises_total'] ?? 0) }}</p>
                    <span class="badge success">Volume consolidado</span>
                </div>

                <div class="card">
                    <h3>Blocos de cardio</h3>
                    <p class="stat-value">{{ (int) ($workoutStatistics['cardio_blocks_total'] ?? 0) }}</p>
                    <span class="badge warning">Condicionamento</span>
                </div>
            </div>

            <div class="stats">
                <div class="card">
                    <h3>Medias de qualidade</h3>
                    <div class="stack-list" style="margin-top: 10px;">
                        @foreach (($workoutStatistics['average_quality_scores'] ?? []) as $score)
                            <div class="mini-card">
                                <strong>{{ (string) ($score['label'] ?? 'Indicador') }}</strong>
                                <small>{{ (int) ($score['value'] ?? 0) }}/100</small>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card">
                    <h3>Referencias recorrentes</h3>
                    <div class="stack-list" style="margin-top: 10px;">
                        @forelse (($workoutStatistics['references'] ?? []) as $reference)
                            <div class="mini-card"><small>{{ (string) $reference }}</small></div>
                        @empty
                            <p>Sem referencias consolidadas ate o momento.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <h3>Melhorias recorrentes</h3>
                    <div class="stack-list" style="margin-top: 10px;">
                        @forelse (($workoutStatistics['improvements'] ?? []) as $improvement)
                            <div class="mini-card"><small>{{ (string) $improvement }}</small></div>
                        @empty
                            <p>Sem melhorias consolidadas ate o momento.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
