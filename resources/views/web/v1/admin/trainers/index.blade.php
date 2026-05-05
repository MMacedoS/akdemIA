@extends('layouts.panel')

@section('pageTitle', 'Trainers')
@section('headerTitle', 'Gerenciar Trainers')

@section('headerAction')
    <a class="btn btn-primary" href="{{ route('admin.trainers.create') }}">Novo trainer</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="stats">
            <div class="card">
                <h3>Total</h3>
                <p class="stat-value">{{ $metrics['total'] }}</p>
            </div>
            <div class="card">
                <h3>Email verificado</h3>
                <p class="stat-value">{{ $metrics['verified'] }}</p>
            </div>
            <div class="card">
                <h3>Com objetivo</h3>
                <p class="stat-value">{{ $metrics['with_goal'] }}</p>
            </div>
        </div>

        <div class="card">
            <form method="GET" class="search-form">
                <input type="text" name="q" value="{{ $search }}" placeholder="Buscar trainer">
                <button type="submit" class="btn btn-soft">Buscar</button>
            </form>
        </div>

        <div class="cards-grid">
            @forelse ($users as $user)
                <article class="user-card">
                    <h4>{{ $user->name }}</h4>
                    <p>{{ $user->email }}</p>
                    <p>Criado em: {{ optional($user->created_at)?->format('d/m/Y H:i') }}</p>
                    <div class="actions">
                        <a class="btn btn-soft" href="{{ route('admin.trainers.show', $user->id) }}">Ver</a>
                        <a class="btn btn-primary" href="{{ route('admin.trainers.edit', $user->id) }}">Editar</a>
                    </div>
                </article>
            @empty
                <div class="card">
                    <p>Nenhum trainer encontrado.</p>
                </div>
            @endforelse
        </div>

        <div class="pagination-wrap">{{ $users->links() }}</div>
    </div>
@endsection
