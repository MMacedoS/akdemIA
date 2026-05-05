@extends('layouts.panel')

@section('pageTitle', 'System Admin - Dashboard')
@section('headerTitle', 'Painel do Sistema')

@section('headerAction')
    <a class="btn btn-primary" href="{{ route('system-admin.users.index') }}">Gerenciar usuarios</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="stats">
            <div class="card">
                <h3>Total de usuarios</h3>
                <p class="stat-value">{{ $summary['total_users'] }}</p>
            </div>
            <div class="card">
                <h3>Total de tenants</h3>
                <p class="stat-value">{{ $summary['total_tenants'] }}</p>
            </div>
            <div class="card">
                <h3>Total de trainers</h3>
                <p class="stat-value">{{ $summary['total_trainees'] }}</p>
            </div>
            <div class="card">
                <h3>Solicitacoes pendentes (PIX)</h3>
                <p class="stat-value">{{ $summary['pending_requests'] }}</p>
            </div>
            <div class="card" style="grid-column: 1 / -1;">
                <h3>Ultima movimentacao financeira</h3>
                <p class="stat-value" style="font-size: 20px;">
                    {{ optional($summary['last_transaction_at'])?->format('d/m/Y H:i') ?? 'Sem transacoes' }}
                </p>
            </div>
        </div>

        <div class="card">
            <h3>Fluxos do System Admin</h3>
            <p>Cada responsabilidade foi separada por dominio com endpoint, controller e tela dedicados.</p>

            <div class="cards-grid" style="margin-top: 12px;">
                <a class="nav-link" href="{{ route('system-admin.tenants.index') }}">
                    <span class="nav-link-left">
                        <span class="nav-icon">TN</span>
                        <span class="nav-label">Tenants</span>
                    </span>
                    <span class="nav-go">&gt;</span>
                </a>

                <a class="nav-link" href="{{ route('system-admin.trainees.index') }}">
                    <span class="nav-link-left">
                        <span class="nav-icon">TR</span>
                        <span class="nav-label">Trainers</span>
                    </span>
                    <span class="nav-go">&gt;</span>
                </a>

                <a class="nav-link" href="{{ route('system-admin.users.index') }}">
                    <span class="nav-link-left">
                        <span class="nav-icon">US</span>
                        <span class="nav-label">Usuarios</span>
                    </span>
                    <span class="nav-go">&gt;</span>
                </a>

                <a class="nav-link" href="{{ route('system-admin.credits.index') }}">
                    <span class="nav-link-left">
                        <span class="nav-icon">CR</span>
                        <span class="nav-label">Creditos e PIX</span>
                    </span>
                    <span class="nav-go">&gt;</span>
                </a>

                <a class="nav-link" href="{{ route('system-admin.settings.payment.edit') }}">
                    <span class="nav-link-left">
                        <span class="nav-icon">PG</span>
                        <span class="nav-label">Settings de pagamento</span>
                    </span>
                    <span class="nav-go">&gt;</span>
                </a>

                <a class="nav-link" href="{{ route('system-admin.settings.email.edit') }}">
                    <span class="nav-link-left">
                        <span class="nav-icon">EM</span>
                        <span class="nav-label">Settings de email</span>
                    </span>
                    <span class="nav-go">&gt;</span>
                </a>

                <a class="nav-link" href="{{ route('system-admin.settings.workoutx.edit') }}">
                    <span class="nav-link-left">
                        <span class="nav-icon">WX</span>
                        <span class="nav-label">Settings WorkoutX</span>
                    </span>
                    <span class="nav-go">&gt;</span>
                </a>

                <a class="nav-link" href="{{ route('system-admin.landing.edit') }}">
                    <span class="nav-link-left">
                        <span class="nav-icon">LG</span>
                        <span class="nav-label">Landing geral</span>
                    </span>
                    <span class="nav-go">&gt;</span>
                </a>
            </div>
        </div>
    </div>
@endsection
