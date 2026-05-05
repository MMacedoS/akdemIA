<div class="form-shell">
    <section class="form-section">
        <h4>Dados basicos do aluno</h4>
        <p>O aluno criado por aqui fica vinculado automaticamente ao trainee atual.</p>

        <div class="form-grid">
            <div class="field">
                <label for="name">Nome</label>
                <input id="name" name="name" type="text" value="{{ old('name', $student->name ?? '') }}" required>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $student->email ?? '') }}" required data-normalize="email" spellcheck="false" autocomplete="email">
            </div>

            <div class="field" style="grid-column: 1 / -1;">
                <label for="password">Senha {{ isset($student) ? '(opcional)' : '' }}</label>
                <input id="password" name="password" type="password" {{ isset($student) ? '' : 'required' }}>
                <small>{{ isset($student) ? 'Preencha apenas para redefinir a senha.' : 'Defina uma senha inicial para o aluno.' }}</small>
            </div>

            <div class="field">
                <label for="birth_date">Data de nascimento</label>
                <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', isset($student) && $student->birth_date ? $student->birth_date->format('Y-m-d') : '') }}">
            </div>

            <div class="field">
                <label for="height">Altura</label>
                <input id="height" name="height" type="number" step="0.01" min="0.5" max="3" value="{{ old('height', $student->height ?? '') }}" placeholder="Ex: 1.75" inputmode="decimal">
            </div>

            <div class="field">
                <label for="weight">Peso</label>
                <input id="weight" name="weight" type="number" step="0.01" min="20" max="500" value="{{ old('weight', $student->weight ?? '') }}" placeholder="Ex: 72.40" inputmode="decimal">
            </div>
        </div>
    </section>

    <section class="form-section">
        <h4>Objetivo</h4>
        <div class="field">
            <label for="goal">Objetivo</label>
            <textarea id="goal" name="goal" placeholder="Ex: Reducao de gordura com reforco de mobilidade">{{ old('goal', $student->goal ?? '') }}</textarea>
        </div>
    </section>
</div>

<div class="actions">
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a href="{{ route('trainee.students.index') }}" class="btn btn-soft">Cancelar</a>
</div>
