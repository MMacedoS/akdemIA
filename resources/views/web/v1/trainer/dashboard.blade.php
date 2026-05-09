@extends('layouts.panel')

@section('pageTitle', 'Dashboard Trainer')
@section('headerTitle', 'Painel do Trainer')

@section('headerAction')
    <a class="btn btn-primary" href="{{ route('trainer.students.index') }}">Ver alunos</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="stats">
            <div class="card">
                <h3>Alunos vinculados</h3>
                <p class="stat-value">{{ $summary['students'] }}</p>
                <span class="badge primary">Base ativa</span>
            </div>

            <div class="card">
                <h3>Treinos pendentes</h3>
                <p class="stat-value">{{ $summary['pending_workouts'] }}</p>
                <span class="badge warning">Aguardando conclusao</span>
            </div>

            <div class="card">
                <h3>Treinos concluidos</h3>
                <p class="stat-value">{{ $summary['completed_workouts'] }}</p>
                <span class="badge success">Historico standalone</span>
            </div>
        </div>

        <div class="card">
            <h3>Funcoes e atribuicoes</h3>
            <p>Como trainer, voce pode acompanhar alunos, consultar dados fisicos e medicos, e monitorar o status dos treinos gerados.</p>
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
                                <a class="btn btn-soft" href="{{ route('trainer.students.show', $student->id) }}">Acompanhar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Nenhum aluno vinculado ao trainer.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
