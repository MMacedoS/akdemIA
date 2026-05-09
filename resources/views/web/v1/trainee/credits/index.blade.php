@extends('layouts.panel')

@section('pageTitle', 'Creditos do Trainee')
@section('headerTitle', 'Creditos e Pagamento')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('trainee.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="stats">
            <div class="card">
                <h3>Saldo atual</h3>
                <p class="stat-value">{{ (int) $trainee->credits_balance }}</p>
                <span class="badge primary">Disponivel para gerar treinos</span>
            </div>

            <div class="card">
                <h3>Custo de geracao</h3>
                <p class="stat-value">2</p>
                <span class="badge warning">Por treino novo</span>
            </div>

            <div class="card">
                <h3>Custo de refazer</h3>
                <p class="stat-value">1</p>
                <span class="badge success">Por ajuste com IA</span>
            </div>
        </div>

        <div class="card" style="max-width: 760px;">
            <h3>Como adquirir creditos</h3>
            <p>Informe a quantidade desejada, gere o QR Code Pix e finalize o pagamento. Os creditos serao liberados automaticamente apos a confirmacao do Mercado Pago.</p>
            <p>Contexto atual: {{ $tenant?->name ?? 'Carteira pessoal do trainee' }}</p>

            <form method="POST" action="{{ route('trainee.credits.store') }}" class="content-stack" style="margin-top: 12px;">
                @csrf

                <div class="form-grid">
                    <div class="field">
                        <label for="credits_requested">Quantidade de creditos</label>
                        <input id="credits_requested" name="credits_requested" type="number" min="1" max="10000" required value="{{ old('credits_requested', 20) }}">
                    </div>

                    <div class="field" style="grid-column: 1 / -1;">
                        <label for="note">Observacao (opcional)</label>
                        <textarea id="note" name="note" placeholder="Ex: credito para novos alunos e refacoes de treino">{{ old('note') }}</textarea>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Gerar QR Code PIX</button>
                </div>
            </form>
        </div>

        @if ($pixKey !== '')
            <div class="card">
                <h3>Chave PIX configurada</h3>
                <p><strong>{{ $pixKey }}</strong></p>
            </div>
        @endif

        <div class="card">
            <h3>Solicitacoes recentes</h3>

            <div class="cards-grid" style="margin-top: 14px;">
                @forelse ($requests as $creditRequest)
                    <article class="user-card">
                        <h4>#{{ $creditRequest->id }} - {{ strtoupper($creditRequest->status) }}</h4>
                        <p>Creditos: {{ $creditRequest->credits_requested }}</p>
                        <p>Criado em: {{ optional($creditRequest->created_at)?->format('d/m/Y H:i') }}</p>
                        <p>Contexto: {{ optional($creditRequest->tenant)->name ?? 'Carteira pessoal' }}</p>
                        @if ($creditRequest->note)
                            <p>Obs: {{ $creditRequest->note }}</p>
                        @endif
                        @if ($creditRequest->payment_status)
                            <p>Status Pix: {{ strtoupper($creditRequest->payment_status) }}@if($creditRequest->payment_status_detail) - {{ $creditRequest->payment_status_detail }}@endif</p>
                        @endif

                        @if ($creditRequest->status === 'pending' && $creditRequest->qr_code_url)
                            <div style="margin-top: 10px;">
                                <img src="{{ $creditRequest->qr_code_url }}" alt="QR Code PIX" style="width: 180px; height: 180px; border: 1px solid #ddd; border-radius: 10px;">
                                <p style="margin-top: 8px; font-size: 12px; color: #666; word-break: break-all;">{{ $creditRequest->pix_payload }}</p>
                                @if ($creditRequest->payment_ticket_url)
                                    <p style="margin-top: 8px;"><a href="{{ $creditRequest->payment_ticket_url }}" target="_blank" rel="noopener noreferrer">Abrir pagina de pagamento Pix</a></p>
                                @endif
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="card">
                        <p>Nenhuma solicitacao de credito registrada ainda.</p>
                    </div>
                @endforelse
            </div>

            <div class="pagination-wrap" style="margin-top: 12px;">{{ $requests->links() }}</div>
        </div>
    </div>
@endsection
