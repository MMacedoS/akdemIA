@extends('layouts.panel')

@section('pageTitle', 'Perfil')
@section('headerTitle', 'Configuracoes de Perfil')

@section('content')
    <section class="card">
        <h3>Atualizar perfil</h3>
        <p>Edite seus dados, foto e informacoes pessoais.</p>

        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-4" style="margin-top: 16px;">
            @csrf
            @method('PATCH')

            <div class="form-grid">
                <div class="field">
                    <label>Avatar atual</label>
                    @if (auth()->user()?->avatar_path)
                        <img src="{{ asset('storage/' . auth()->user()->avatar_path) }}" alt="Avatar" style="width:72px;height:72px;border-radius:999px;object-fit:cover;border:1px solid #e6e6ef;">
                    @else
                        <div style="width:72px;height:72px;border-radius:999px;background:#ecebff;color:#7367f0;display:flex;align-items:center;justify-content:center;font-weight:700;">
                            {{ strtoupper(substr((string) auth()->user()?->name, 0, 1)) }}
                        </div>
                    @endif
                </div>

                <div class="field">
                    <label for="avatar">Trocar imagem</label>
                    <input id="avatar" name="avatar" type="file" accept="image/png,image/jpeg,image/webp">
                    <label style="display:flex;align-items:center;gap:8px;color:#8d8a98;">
                        <input type="checkbox" name="remove_avatar" value="1">
                        <span>Remover avatar atual</span>
                    </label>
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="name">Nome</label>
                    <input id="name" name="name" type="text" value="{{ old('name', auth()->user()?->name) }}" required>
                </div>
                <div class="field">
                    <label for="email">E-mail</label>
                    <input id="email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" required data-normalize="email" spellcheck="false" autocomplete="email">
                </div>

                <div class="field">
                    <label for="birth_date">Nascimento</label>
                    <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', optional(auth()->user()?->birth_date)->format('Y-m-d')) }}" lang="pt-BR">
                </div>

                <div class="field">
                    <label for="gender">Genero</label>
                    <input id="gender" name="gender" type="text" value="{{ old('gender', auth()->user()?->gender) }}" placeholder="Ex: Feminino">
                </div>

                <div class="field">
                    <label for="height">Altura</label>
                    <input id="height" name="height" type="number" step="0.01" value="{{ old('height', auth()->user()?->height) }}" placeholder="Ex: 1.75" data-mask="decimal" inputmode="decimal">
                </div>

                <div class="field">
                    <label for="weight">Peso</label>
                    <input id="weight" name="weight" type="number" step="0.01" value="{{ old('weight', auth()->user()?->weight) }}" placeholder="Ex: 72.40" data-mask="decimal" inputmode="decimal">
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <label for="goal">Objetivo</label>
                    <input id="goal" name="goal" type="text" value="{{ old('goal', auth()->user()?->goal) }}" placeholder="Ex: Hipertrofia e condicionamento">
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </section>
@endsection
