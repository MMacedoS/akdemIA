@extends('layouts.panel')

@section('pageTitle', 'System Admin - Catalogos Publicos')
@section('headerTitle', 'Preco dos Catalogos Publicos')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="card">
            <form method="GET" class="search-form">
                <input type="text" name="q" value="{{ $search }}" placeholder="Buscar catalogo publico">
                <button class="btn btn-soft" type="submit">Buscar</button>
            </form>
        </div>

        <div class="card">
            <h3>Catalogos publicos com preco editavel</h3>
            <p style="margin-top: 6px; color: var(--text-muted);">Apenas catalogos com uso publico habilitado podem receber preco comercial.</p>

            <div class="cards-grid" style="margin-top: 12px;">
                @forelse ($catalogs as $catalog)
                    <article class="user-card">
                        <h4>{{ $catalog->name }}</h4>
                        <p>{{ \Illuminate\Support\Str::limit((string) $catalog->description, 140) }}</p>
                        <p>Exercicios: {{ (int) $catalog->quantity_exercises }} | Ativo: {{ (bool) $catalog->status ? 'Sim' : 'Nao' }}</p>
                        <p>Criador: {{ $catalog->owner?->name ?? 'Sistema' }} {{ $catalog->owner?->email ? '(' . $catalog->owner->email . ')' : '' }}</p>
                        <p>Criado em: {{ optional($catalog->created_at)?->format('d/m/Y H:i') }}</p>

                        <form method="POST" action="{{ route('system-admin.workouts.catalogs.price.update', $catalog->id) }}" class="actions" style="gap: 8px; align-items: center; flex-wrap: wrap;">
                            @csrf
                            @method('PUT')

                            <label style="display: grid; gap: 4px; min-width: 140px;">
                                <span style="font-size: 12px; color: var(--text-muted);">Preco</span>
                                <input type="number" name="price" min="0" max="999999" value="{{ old('price.' . $catalog->id, (int) $catalog->price) }}" required>
                            </label>

                            <button class="btn btn-primary" type="submit">Salvar preco</button>
                        </form>
                    </article>
                @empty
                    <div class="card">
                        <p>Nenhum catalogo publico encontrado.</p>
                    </div>
                @endforelse
            </div>

            <div class="pagination-wrap">{{ $catalogs->links() }}</div>
        </div>
    </div>
@endsection
