@extends('layouts.panel')

@section('pageTitle', 'System Admin - Settings de Pagamento')
@section('headerTitle', 'Settings de Pagamento')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="card" style="max-width: 860px;">
        <h3>Configuracao do Mercado Pago</h3>
        <p>Este ambiente usa somente Mercado Pago para Pix. Informe as credenciais do provedor e o segredo do webhook.</p>

        <form method="POST" action="{{ route('system-admin.settings.payment.update') }}" class="content-stack" style="margin-top: 12px;">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field">
                    <label>Provedor</label>
                    <input type="text" value="Mercado Pago" disabled>
                    <small>O provedor de pagamento desta aplicacao esta fixado em Mercado Pago.</small>
                </div>

                <div class="field">
                    <label for="payment_api_base_url">API Base URL</label>
                    <input id="payment_api_base_url" name="payment_api_base_url" type="url" maxlength="2000" value="{{ old('payment_api_base_url', $settings->get('payment.api_base_url')) }}" inputmode="url" spellcheck="false">
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <label for="payment_api_token">Token da API (segredo)</label>
                    <input id="payment_api_token" name="payment_api_token" type="password" maxlength="2000" placeholder="Preencha para atualizar o access token do Mercado Pago">
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
                    <label for="payment_mercadopago_webhook_secret">Segredo do webhook</label>
                    <input id="payment_mercadopago_webhook_secret" name="payment_mercadopago_webhook_secret" type="password" maxlength="255" placeholder="Preencha para atualizar o segredo compartilhado do webhook">
                    <small>
                        Status atual:
                        @if (filled($settings->get('payment.mercadopago_webhook_secret')))
                            <span class="badge success">Configurado</span>
                        @else
                            <span class="badge warning">Nao configurado</span>
                        @endif
                    </small>
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <label>URL sugerida para o webhook</label>
                    <input type="text" value="{{ url('/api/v1/billing/mercadopago/webhook') }}?secret=SEU_SEGREDO" readonly>
                    <small>Cadastre esta URL no painel do Mercado Pago substituindo SEU_SEGREDO pelo mesmo valor salvo acima.</small>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Salvar configuracoes</button>
            </div>
        </form>
    </div>
@endsection
