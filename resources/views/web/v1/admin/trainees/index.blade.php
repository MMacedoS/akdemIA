@extends('layouts.panel')

@section('pageTitle', 'Trainees do Tenant')
@section('headerTitle', 'Gerenciar Trainees')

@section('headerAction')
    <a class="btn btn-primary" href="{{ route('admin.trainees.create') }}">Novo trainee</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="stats">
            <div class="card">
                <h3>Total</h3>
                <p class="stat-value">{{ $metrics['total'] }}</p>
            </div>
            <div class="card">
                <h3>Ativos</h3>
                <p class="stat-value">{{ $metrics['active'] }}</p>
            </div>
            <div class="card">
                <h3>Com alunos</h3>
                <p class="stat-value">{{ $metrics['with_students'] }}</p>
            </div>
        </div>

        <div class="card">
            <form method="GET" class="search-form">
                <input type="text" name="q" value="{{ $search }}" placeholder="Buscar trainee">
                <button type="submit" class="btn btn-soft">Buscar</button>
            </form>
        </div>

        <div class="cards-grid">
            @forelse ($trainees as $trainee)
                <article class="user-card">
                    <h4>{{ $trainee->name }}</h4>
                    <p>{{ $trainee->email }}</p>
                    <p>Criado em: {{ optional($trainee->created_at)?->format('d/m/Y H:i') }}</p>
                    <div class="actions">
                        <a class="btn btn-soft" href="{{ route('admin.trainees.show', $trainee->id) }}">Ver</a>
                        <a class="btn btn-primary" href="{{ route('admin.trainees.edit', $trainee->id) }}">Editar</a>
                    </div>
                </article>
            @empty
                <div class="card">
                    <p>Nenhum trainee vinculado a este tenant.</p>
                </div>
            @endforelse
        </div>

        <div class="pagination-wrap">{{ $trainees->links() }}</div>
    </div>
@endsection
