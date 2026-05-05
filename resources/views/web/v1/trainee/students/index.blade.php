@extends('layouts.panel')

@section('pageTitle', 'Alunos do Trainee')
@section('headerTitle', 'Acompanhamento de Alunos')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('tenants.select') }}">Trocar tenant</a>
    <a class="btn btn-primary" href="{{ route('trainee.students.create') }}">Novo aluno</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="card">
            <h3>Lista de alunos permitidos</h3>
            <p>
                @if ($tenant)
                    Visualizacao, acompanhamento e geracao de treino dos alunos vinculados ao tenant {{ $tenant->name }}.
                @else
                    Visualizacao da sua carteira pessoal de alunos. Para gerar treino, selecione um tenant.
                @endif
            </p>
        </div>

        <div class="card">
            <form method="GET" class="search-form">
                <input type="text" name="q" value="{{ $search }}" placeholder="Buscar aluno por nome ou email">
                <button type="submit" class="btn btn-soft">Buscar</button>
            </form>
        </div>

        <div class="cards-grid">
            @forelse ($students as $student)
                <article class="user-card">
                    <h4>{{ $student->name }}</h4>
                    <p>{{ $student->email }}</p>
                    <p>Objetivo: {{ $student->goal ?: 'Nao informado' }}</p>
                    <div class="actions">
                        <a class="btn btn-soft" href="{{ route('trainee.students.show', $student->id) }}">Visualizar</a>
                        <a class="btn btn-primary" href="{{ route('trainee.students.edit', $student->id) }}">Editar saude</a>
                    </div>
                </article>
            @empty
                <div class="card">
                    <p>Nenhum aluno vinculado a este trainee.</p>
                </div>
            @endforelse
        </div>

        <div class="pagination-wrap">{{ $students->links() }}</div>
    </div>
@endsection
