@extends('layouts.panel')

@section('pageTitle', 'Dashboard Admin')
@section('headerTitle', 'Dashboard Admin')

@section('content')
    <div class="stats">
        <div class="card">
            <h3>Total de alunos</h3>
            <p class="stat-value">{{ $summary['total_students'] }}</p>
            <span class="badge primary">Ativos no tenant</span>
        </div>

        <div class="card">
            <h3>Total de trainers</h3>
            <p class="stat-value">{{ $summary['total_trainers'] }}</p>
            <span class="badge success">Equipe tecnica</span>
        </div>

        <div class="card">
            <h3>Creditos disponiveis</h3>
            <p class="stat-value">{{ $summary['credits_balance'] }}</p>
            <span class="badge warning">Plano atual: {{ $summary['current_plan'] }}</span>
        </div>
    </div>
@endsection
