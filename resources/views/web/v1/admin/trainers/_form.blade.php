<div class="form-shell">
    <section class="form-section">
        <h4>Dados do trainer</h4>
        <p>Preencha os dados de identificacao e acesso do profissional.</p>

        <div class="form-grid">
            <div class="field">
                <label for="name">Nome</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" required data-normalize="email" spellcheck="false" autocomplete="email">
            </div>

            <div class="field" style="grid-column: 1 / -1;">
                <label for="password">Senha {{ isset($user) ? '(opcional)' : '' }}</label>
                <input id="password" name="password" type="password" {{ isset($user) ? '' : 'required' }}>
                <small>{{ isset($user) ? 'Preencha apenas se houver troca de credencial.' : 'Informe uma senha inicial de acesso.' }}</small>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h4>Especialidade e foco</h4>
        <p>Defina como o trainer vai atuar no tenant.</p>

        <div class="field">
            <label for="goal">Objetivo</label>
            <textarea id="goal" name="goal" placeholder="Ex: Hipertrofia, treino funcional e acompanhamento semanal">{{ old('goal', $user->goal ?? '') }}</textarea>
        </div>
    </section>
</div>

<div class="actions">
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a href="{{ route('admin.trainers.index') }}" class="btn btn-soft">Cancelar</a>
</div>
