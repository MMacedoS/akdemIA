<div class="form-shell">
    <section class="form-section">
        <h4>Dados basicos do aluno</h4>
        <p>Esses dados identificam o aluno e permitem o acesso inicial.</p>

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
                <small>{{ isset($user) ? 'Preencha apenas para redefinir a senha.' : 'Defina uma senha de acesso inicial para o aluno.' }}</small>
            </div>

            <div class="field" style="grid-column: 1 / -1;">
                <label for="trainee_user_id">Trainee responsavel</label>
                <select id="trainee_user_id" name="trainee_user_id">
                    <option value="">Sem trainee vinculado</option>
                    @foreach (($traineeOptions ?? collect()) as $traineeOption)
                        <option value="{{ $traineeOption->id }}" @selected((int) old('trainee_user_id', $assignedTrainee->id ?? 0) === (int) $traineeOption->id)>
                            {{ $traineeOption->name }} ({{ $traineeOption->email }})
                        </option>
                    @endforeach
                </select>
                <small>Esse vinculo define quais alunos cada trainee pode gerenciar.</small>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h4>Objetivo</h4>
        <p>Use esse campo para indicar o foco do plano do aluno.</p>

        <div class="field">
            <label for="goal">Objetivo</label>
            <textarea id="goal" name="goal" placeholder="Ex: Reabilitacao de joelho com perda de peso">{{ old('goal', $user->goal ?? '') }}</textarea>
        </div>
    </section>
</div>

<div class="actions">
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a href="{{ route('admin.students.index') }}" class="btn btn-soft">Cancelar</a>
</div>
