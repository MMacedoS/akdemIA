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
    </div>
@endsection
