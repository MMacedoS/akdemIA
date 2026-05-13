@extends('layouts.panel')

@section('pageTitle', 'System Admin - Google Auth')
@section('headerTitle', 'Google Auth')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="card" style="max-width: 980px;">
        <h3>Credenciais de autenticacao Google</h3>
        <p>Configure o login social do sistema pelo painel. Se o segredo ficar em branco, o valor atual salvo sera mantido.</p>

        <form method="POST" action="{{ route('system-admin.settings.google-auth.update') }}" class="content-stack" style="margin-top: 12px;">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field">
                    <label for="google_client_id">Google Client ID</label>
                    <input id="google_client_id" name="google_client_id" type="text" maxlength="255" value="{{ old('google_client_id', $settings->get('google.client_id')) }}">
                </div>

                <div class="field">
                    <label for="google_client_secret">Google Client Secret</label>
                    <input id="google_client_secret" name="google_client_secret" type="password" maxlength="255" placeholder="Preencha apenas para atualizar o segredo">
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <label for="google_redirect_uri">Google Redirect URI</label>
                    <input id="google_redirect_uri" name="google_redirect_uri" type="url" maxlength="2000" value="{{ old('google_redirect_uri', $settings->get('google.redirect_uri')) }}" placeholder="https://seu-dominio.com/auth/google/callback" spellcheck="false">
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Salvar configuracoes</button>
            </div>
        </form>
    </div>
@endsection
