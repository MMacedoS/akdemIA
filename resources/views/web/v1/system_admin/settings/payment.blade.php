@extends('layouts.panel')

@section('pageTitle', 'System Admin - Settings de Pagamento')
@section('headerTitle', 'Settings de Pagamento')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="card" style="max-width: 860px;">
        <h3>Parametros internos de pagamento</h3>
        <p>Dominio separado para gateways e credenciais de API.</p>

        <form method="POST" action="{{ route('system-admin.settings.payment.update') }}" class="content-stack" style="margin-top: 12px;">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field">
                    <label for="payment_provider_name">Provedor</label>
                    <input id="payment_provider_name" name="payment_provider_name" type="text" maxlength="120" value="{{ old('payment_provider_name', $settings->get('payment.provider_name')) }}">
                </div>

                <div class="field">
                    <label for="payment_api_base_url">API Base URL</label>
                    <input id="payment_api_base_url" name="payment_api_base_url" type="url" maxlength="2000" value="{{ old('payment_api_base_url', $settings->get('payment.api_base_url')) }}" inputmode="url" spellcheck="false">
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <label for="payment_api_token">Token da API (segredo)</label>
                    <input id="payment_api_token" name="payment_api_token" type="password" maxlength="2000" placeholder="Preencha para atualizar o token">
                    <small>
                        Status atual:
                        @if (filled($settings->get('payment.api_token')))
                            <span class="badge success">Configurado</span>
                        @else
                            <span class="badge warning">Nao configurado</span>
                        @endif
                    </small>
                </div>

                <div class="field">
                    <label for="payment_pix_key">Chave PIX</label>
                    <input id="payment_pix_key" name="payment_pix_key" type="password" maxlength="255" placeholder="Preencha para atualizar a chave PIX">
                    <small>
                        Status atual:
                        @if (filled($settings->get('payment.pix_key')))
                            <span class="badge success">Configurado</span>
                        @else
                            <span class="badge warning">Nao configurado</span>
                        @endif
                    </small>
                </div>

                <div class="field">
                    <label for="payment_stripe_secret">Stripe secret</label>
                    <input id="payment_stripe_secret" name="payment_stripe_secret" type="password" maxlength="255" placeholder="Preencha para atualizar a chave secreta Stripe">
                    <small>
                        Status atual:
                        @if (filled($settings->get('payment.stripe_secret')))
                            <span class="badge success">Configurado</span>
                        @else
                            <span class="badge warning">Nao configurado</span>
                        @endif
                    </small>
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <label for="payment_stripe_webhook_secret">Stripe webhook secret</label>
                    <input id="payment_stripe_webhook_secret" name="payment_stripe_webhook_secret" type="password" maxlength="255" placeholder="Preencha para atualizar o segredo do webhook Stripe">
                    <small>
                        Status atual:
                        @if (filled($settings->get('payment.stripe_webhook_secret')))
                            <span class="badge success">Configurado</span>
                        @else
                            <span class="badge warning">Nao configurado</span>
                        @endif
                    </small>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Salvar configuracoes</button>
            </div>
        </form>
    </div>
@endsection
