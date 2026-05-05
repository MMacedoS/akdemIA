@extends('layouts.panel')

@section('pageTitle', 'Seguranca')
@section('headerTitle', 'Configuracoes de Seguranca')

@section('content')
    <section class="card">
        <h3>Alterar senha</h3>
        <p>Atualize sua senha de acesso.</p>

        <form method="POST" action="{{ route('user-password.update') }}" class="space-y-4" style="margin-top: 16px;">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field">
                    <label for="current_password">Senha atual</label>
                    <input id="current_password" name="current_password" type="password" required>
                </div>
                <div class="field">
                    <label for="password">Nova senha</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirmar nova senha</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Atualizar senha</button>
            </div>
        </form>
    </section>
@endsection
