@extends('layouts.panel')

@section('pageTitle', 'Catalogo de Treinos')
@section('headerTitle', 'Catalogo de Treinos')

@section('headerAction')
    <a class="btn btn-primary" href="{{ route($routePrefix . '.create') }}">Novo catalogo</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="card">
            <form method="GET" class="search-form">
                <input type="text" name="q" value="{{ $search }}" placeholder="Buscar catalogo por nome ou descricao">
                <button type="submit" class="btn btn-soft">Buscar</button>
            </form>
        </div>

        <div class="cards-grid">
            @forelse ($catalogs as $catalog)
                @php
                    $isOwner = (int) $catalog->user_id === (int) auth()->id();
                    $canManage = $panel === 'admin' || $isOwner;
                @endphp
                <article class="user-card">
                    <h4>{{ $catalog->name }}</h4>
                    <p>{{ \Illuminate\Support\Str::limit((string) $catalog->description, 140) }}</p>
                    <p>Exercicios: {{ (int) $catalog->quantity_exercises }} | Preco: {{ (int) $catalog->price }}</p>
                    <p>Visibilidade: {{ (bool) $catalog->is_public ? 'Publico' : 'Privado' }}</p>
                    <p>Status: {{ (bool) $catalog->status ? 'Ativo' : 'Inativo' }}</p>
                    <p>Responsavel: {{ $catalog->owner?->name ?? 'Sistema' }}</p>
                    <p>Criado em: {{ optional($catalog->created_at)?->format('d/m/Y H:i') }}</p>

                    <div class="actions">
                        @if ($canManage)
                            <a class="btn btn-primary" href="{{ route($routePrefix . '.edit', $catalog->id) }}">Editar</a>

                            <form method="POST" action="{{ route($routePrefix . '.destroy', $catalog->id) }}" onsubmit="return confirm('Deseja remover este catalogo?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-soft" type="submit">Excluir</button>
                            </form>
                        @else
                            <span class="btn btn-soft" style="pointer-events: none; opacity: 0.6;">Somente leitura</span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="card">
                    <p>Nenhum catalogo encontrado.</p>
                </div>
            @endforelse
        </div>

        <div class="pagination-wrap">{{ $catalogs->links() }}</div>
    </div>
@endsection
