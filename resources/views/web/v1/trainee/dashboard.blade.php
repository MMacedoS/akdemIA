@extends('layouts.panel')

@section('pageTitle', 'Dashboard Trainee')
@section('headerTitle', 'Painel do Trainee')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('trainee.credits.index') }}">Creditos</a>
    <a class="btn btn-primary" href="{{ route('trainee.students.index') }}">Ver alunos</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="stats">
            <div class="card">
                <h3>Alunos no contexto</h3>
                <p class="stat-value">{{ $summary['students'] }}</p>
                <span class="badge primary">{{ $activeContextLabel }}</span>
            </div>

            <div class="card">
                <h3>Treinos pendentes</h3>
                <p class="stat-value">{{ $summary['pending_workouts'] }}</p>
                <span class="badge warning">Aguardando conclusao</span>
            </div>

            <div class="card">
                <h3>Treinos concluidos</h3>
                <p class="stat-value">{{ $summary['completed_workouts'] }}</p>
                <span class="badge success">Historico do trainee</span>
            </div>

            <div class="card">
                <h3>Creditos disponiveis</h3>
                <p class="stat-value">{{ $summary['credits_balance'] }}</p>
                <span class="badge primary">2 por geracao e 1 por refazer</span>
            </div>
        </div>

        <div class="card">
            <h3>Funcoes e atribuicoes</h3>
            <p>Como trainee, voce pode acompanhar alunos, consultar dados fisicos e medicos, gerar treino com ou sem tenant selecionado e consumir creditos do proprio perfil.</p>
        </div>

        <div class="card">
            <h3>Como adquirir creditos</h3>
            <p>Abra a area de creditos, informe a quantidade desejada e gere um QR Code PIX. Apos a aprovacao do pagamento, os creditos entram no saldo do trainee.</p>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Aluno</th>
                        <th>Email</th>
                        <th>Objetivo</th>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentStudents as $student)
                        <tr>
                            <td>{{ $student->name }}</td>
                            <td>{{ $student->email }}</td>
                            <td>{{ $student->goal ?: 'Nao informado' }}</td>
                            <td>
                                <a class="btn btn-soft" href="{{ route('trainee.students.show', $student->id) }}">Acompanhar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Nenhum aluno encontrado neste contexto.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
