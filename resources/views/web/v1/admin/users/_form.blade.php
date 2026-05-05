<div class="form-shell">
    <section class="form-section">
        <h4>Dados de acesso</h4>
        <p>Informacoes principais para cadastro do usuario administrador.</p>

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
                <small>{{ isset($user) ? 'Preencha apenas se quiser trocar a senha atual.' : 'Use uma senha forte com no minimo 8 caracteres.' }}</small>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h4>Objetivo do perfil</h4>
        <p>Campo opcional para registrar foco principal do usuario.</p>

        <div class="field">
            <label for="goal">Objetivo</label>
            <textarea id="goal" name="goal" placeholder="Ex: Gestao de alunos e acompanhamento de indicadores">{{ old('goal', $user->goal ?? '') }}</textarea>
        </div>
    </section>
</div>

<div class="actions">
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a href="{{ route('admin.users.index') }}" class="btn btn-soft">Cancelar</a>
</div>
