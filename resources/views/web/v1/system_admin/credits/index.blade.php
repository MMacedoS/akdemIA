@extends('layouts.panel')

@section('pageTitle', 'System Admin - Creditos')
@section('headerTitle', 'Creditos e PIX')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="stats">
            <div class="card">
                <h3>Solicitacoes pendentes</h3>
                <p class="stat-value">{{ $summary['pending_requests'] }}</p>
            </div>
            <div class="card">
                <h3>Solicitacoes aprovadas</h3>
                <p class="stat-value">{{ $summary['approved_requests'] }}</p>
            </div>
            <div class="card">
                <h3>Creditos concedidos</h3>
                <p class="stat-value">{{ $summary['total_credit_granted'] }}</p>
            </div>
            <div class="card">
                <h3>Creditos consumidos</h3>
                <p class="stat-value">{{ $summary['total_credit_consumed'] }}</p>
            </div>
        </div>

        <div class="card" style="max-width: 860px;">
            <h3>Conceder creditos manualmente</h3>
            <form method="POST" action="{{ route('system-admin.credits.grant') }}" class="content-stack" style="margin-top: 12px;">
                @csrf

                <div class="form-grid">
                    <div class="field" style="grid-column: 1 / -1;">
                        <label for="target_user_id">Usuario</label>
                        <select id="target_user_id" name="target_user_id" required>
                            <option value="">Selecione</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((int) old('target_user_id') === (int) $user->id)>
                                    {{ $user->name }} ({{ $user->email }}) - saldo: {{ (int) $user->credits_balance }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="credits">Creditos</label>
                        <input id="credits" name="credits" type="number" min="1" max="50000" required value="{{ old('credits', 10) }}">
                    </div>

                    <div class="field">
                        <label for="note">Observacao</label>
                        <input id="note" name="note" type="text" maxlength="500" value="{{ old('note') }}">
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Adicionar creditos</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Solicitacoes pendentes (PIX)</h3>
            <div class="cards-grid" style="margin-top: 12px;">
                @forelse ($pendingRequests as $requestItem)
                    <article class="user-card">
                        <h4>#{{ $requestItem->id }} - {{ $requestItem->credits_requested }} creditos</h4>
                        <p>Solicitante: {{ $requestItem->requester?->name }} ({{ $requestItem->requester?->email }})</p>
                        <p>Tenant: {{ $requestItem->tenant?->name ?? 'Sem tenant' }}</p>
                        <p>Chave PIX: {{ $requestItem->pix_key }}</p>
                        @if ($requestItem->payment_status)
                            <p>Status Pix: {{ strtoupper($requestItem->payment_status) }}@if($requestItem->payment_status_detail) - {{ $requestItem->payment_status_detail }}@endif</p>
                        @endif

                        @if ($requestItem->qr_code_url)
                            <img src="{{ $requestItem->qr_code_url }}" alt="QR Code PIX" style="width: 150px; height: 150px; border-radius: 8px; border: 1px solid #ddd; margin: 10px 0;">
                        @endif

                        @if ($requestItem->payment_ticket_url)
                            <p><a href="{{ $requestItem->payment_ticket_url }}" target="_blank" rel="noopener noreferrer">Abrir pagina de pagamento Pix</a></p>
                        @endif

                        <div class="actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
                            @if ($requestItem->payment_external_reference === null || $requestItem->payment_status === 'approved')
                                <form method="POST" action="{{ route('system-admin.requests.approve', $requestItem->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary">Aprovar</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('system-admin.requests.reject', $requestItem->id) }}" style="display: flex; gap: 6px; align-items: center;">
                                @csrf
                                <input type="text" name="reason" placeholder="Motivo (opcional)" style="min-width: 140px;">
                                <button type="submit" class="btn btn-soft">Rejeitar</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="card">
                        <p>Sem solicitacoes pendentes.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <h3>Transacoes recentes</h3>
            <div class="cards-grid" style="margin-top: 12px;">
                @forelse ($recentTransactions as $transaction)
                    <article class="user-card">
                        <h4>{{ $transaction->type }}</h4>
                        <p>Usuario: {{ $transaction->user?->email }}</p>
                        <p>Valor: {{ $transaction->amount }}</p>
                        <p>Ator: {{ $transaction->actor?->email ?? 'sistema' }}</p>
                        <p>Quando: {{ optional($transaction->created_at)?->format('d/m/Y H:i') }}</p>
                    </article>
                @empty
                    <div class="card">
                        <p>Nenhuma transacao registrada.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
