@extends('layouts.panel')

@section('pageTitle', 'System Admin - Settings de Email')
@section('headerTitle', 'Settings de Email')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="card" style="max-width: 980px;">
        <h3>Parametros internos de envio de email</h3>
        <p>Dominio separado para configuracao SMTP do sistema.</p>

        <form method="POST" action="{{ route('system-admin.settings.email.update') }}" class="content-stack" style="margin-top: 12px;">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field">
                    <label for="mail_mailer">Mailer</label>
                    <input id="mail_mailer" name="mail_mailer" type="text" maxlength="40" value="{{ old('mail_mailer', $settings->get('mail.mailer')) }}" placeholder="smtp">
                </div>

                <div class="field">
                    <label for="mail_host">Host</label>
                    <input id="mail_host" name="mail_host" type="text" maxlength="255" value="{{ old('mail_host', $settings->get('mail.host')) }}">
                </div>

                <div class="field">
                    <label for="mail_port">Porta</label>
                    <input id="mail_port" name="mail_port" type="number" min="1" max="65535" value="{{ old('mail_port', $settings->get('mail.port')) }}" data-mask="decimal" inputmode="numeric">
                </div>

                <div class="field">
                    <label for="mail_username">Usuario</label>
                    <input id="mail_username" name="mail_username" type="text" maxlength="255" value="{{ old('mail_username', $settings->get('mail.username')) }}">
                </div>

                <div class="field">
                    <label for="mail_password">Senha (segredo)</label>
                    <input id="mail_password" name="mail_password" type="password" maxlength="255" placeholder="Preencha para atualizar a senha SMTP">
                </div>

                <div class="field">
                    <label for="mail_encryption">Criptografia</label>
                    <input id="mail_encryption" name="mail_encryption" type="text" maxlength="30" value="{{ old('mail_encryption', $settings->get('mail.encryption')) }}" placeholder="tls para 587 ou ssl para 465">
                </div>

                <div class="field">
                    <label for="mail_from_address">From address</label>
                    <input id="mail_from_address" name="mail_from_address" type="email" maxlength="255" value="{{ old('mail_from_address', $settings->get('mail.from_address')) }}" data-normalize="email" spellcheck="false" autocomplete="email">
                </div>

                <div class="field">
                    <label for="mail_from_name">From name</label>
                    <input id="mail_from_name" name="mail_from_name" type="text" maxlength="120" value="{{ old('mail_from_name', $settings->get('mail.from_name')) }}">
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Salvar configuracoes</button>
            </div>
        </form>
    </div>
@endsection
