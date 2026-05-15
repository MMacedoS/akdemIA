@extends('layouts.panel')

@section('pageTitle', isset($catalog->id) ? 'Editar Catalogo de Treinos' : 'Novo Catalogo de Treinos')
@section('headerTitle', isset($catalog->id) ? 'Editar Catalogo de Treinos' : 'Novo Catalogo de Treinos')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route($routePrefix) }}">Voltar para catalogos</a>
@endsection

@section('content')
    @php
        $isEditing = isset($catalog->id);
        $actionRoute = $isEditing ? route($routePrefix . '.update', $catalog->id) : route($routePrefix . '.store');
        $formId = $isEditing ? 'catalog-update-form' : 'catalog-create-form';
        $resolvedSelectedExerciseIds = collect(old('exercise_media_cache_ids', $selectedExerciseIds))
            ->map(static fn($id) => (int) $id)
            ->all();
    @endphp

    <div class="content-stack">
        <form id="{{ $formId }}" method="POST" action="{{ $actionRoute }}" class="card" style="display: grid; gap: 14px;">
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif

            <div class="field" style="max-width: 100%;">
                <label for="name">Nome do treino</label>
                <input id="name" name="name" type="text" maxlength="60" value="{{ old('name', $catalog->name) }}" required>
            </div>

            <div class="field" style="max-width: 100%;">
                <label for="description">Descricao</label>
                <textarea id="description" name="description" rows="4" maxlength="4000" required>{{ old('description', $catalog->description) }}</textarea>
            </div>

            <div class="stats" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="field" style="max-width: 100%; margin: 0;">
                    <label for="price">Preco (creditos)</label>
                    <input id="price" name="price" type="number" min="1" max="999999" value="{{ old('price', $catalog->price ?: 1) }}" required>
                </div>

                <div class="field" style="max-width: 100%; margin: 0;">
                    <label for="path_image">Caminho da imagem (opcional)</label>
                    <input id="path_image" name="path_image" type="text" maxlength="100" value="{{ old('path_image', $catalog->path_image) }}">
                </div>
            </div>

            <div class="stats" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                <label class="card" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                    <input type="hidden" name="is_public" value="0">
                    <input type="checkbox" name="is_public" value="1" {{ (bool) old('is_public', $catalog->is_public ?? false) ? 'checked' : '' }}>
                    Disponivel para uso publico
                </label>

                <label class="card" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                    <input type="hidden" name="status" value="0">
                    <input type="checkbox" name="status" value="1" {{ (bool) old('status', $catalog->status ?? true) ? 'checked' : '' }}>
                    Catalogo ativo
                </label>
            </div>

            <div class="actions">
                <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
            </div>
        </form>

        <div class="card" style="display: grid; gap: 12px;">
            <h3 style="margin: 0;">Exercicios do catalogo</h3>
            <p style="margin: 0;">Selecione os exercicios que vao compor este treino pronto para trainer e admin.</p>

            <form method="GET" class="search-form">
                <input type="text" name="exercise_search" value="{{ $exerciseSearch }}" placeholder="Buscar exercicio por nome, alias ou origem">
                <button class="btn btn-soft" type="submit">Filtrar exercicios</button>
            </form>

            <div style="display: grid; gap: 10px; max-height: 420px; overflow: auto; border: 1px solid var(--border); border-radius: 10px; padding: 10px;">
                @forelse ($exerciseOptions as $exercise)
                    @php
                        $checked = in_array((int) $exercise['id'], $resolvedSelectedExerciseIds, true);
                    @endphp
                    <label style="display: grid; grid-template-columns: auto 1fr; gap: 10px; align-items: start; border: 1px solid var(--border); border-radius: 8px; padding: 8px;">
                        <input type="checkbox" name="exercise_media_cache_ids[]" value="{{ $exercise['id'] }}" form="{{ $formId }}" {{ $checked ? 'checked' : '' }}>
                        <span>
                            <strong>{{ $exercise['name'] }}</strong><br>
                            <small>Foco: {{ $exercise['focus'] ?: 'geral' }}{{ $exercise['target'] !== '' ? ' | Alvo: ' . $exercise['target'] : '' }}</small>
                        </span>
                    </label>
                @empty
                    <p style="margin: 0;">Nenhum exercicio encontrado.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
