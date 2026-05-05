<div class="form-shell">
    <section class="form-section">
        <h4>Dados basicos do trainee</h4>
        <p>Esses dados identificam o trainee e liberam o acesso ao tenant.</p>

        <div class="form-grid">
            <div class="field">
                <label for="name">Nome</label>
                <input id="name" name="name" type="text" value="{{ old('name', $trainee->name ?? '') }}" required>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $trainee->email ?? '') }}" required data-normalize="email" spellcheck="false" autocomplete="email">
            </div>

            <div class="field" style="grid-column: 1 / -1;">
                <label for="password">Senha {{ isset($trainee) ? '(opcional)' : '' }}</label>
                <input id="password" name="password" type="password" {{ isset($trainee) ? '' : 'required' }}>
                <small>{{ isset($trainee) ? 'Preencha apenas para redefinir a senha.' : 'Defina uma senha inicial para o trainee.' }}</small>
            </div>
        </div>
    </section>
</div>

<div class="actions">
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a href="{{ route('admin.trainees.index') }}" class="btn btn-soft">Cancelar</a>
</div>
