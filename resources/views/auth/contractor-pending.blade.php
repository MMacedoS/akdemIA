@extends('layouts.auth')

@section('title', 'Cadastro de Contratante')

@section('content')
    <style>
        .contractor-pending {
            display: grid;
            gap: 18px;
        }

        .contractor-form {
            display: grid;
            gap: 14px;
        }

        .contractor-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .contractor-field {
            display: grid;
            gap: 8px;
        }

        .contractor-field-full {
            grid-column: 1 / -1;
        }

        .contractor-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #50615a;
            font-weight: 700;
        }

        .contractor-panel {
            padding: 20px;
            border-radius: 24px;
            background:
                linear-gradient(135deg, rgba(16, 37, 33, 0.98), rgba(55, 104, 88, 0.92));
            color: #f4f7f5;
        }

        .contractor-panel h2 {
            margin: 0 0 10px;
            font-size: 26px;
            line-height: 1.05;
        }

        .contractor-panel p,
        .contractor-list,
        .contractor-helper {
            margin: 0;
            line-height: 1.7;
            color: rgba(244, 247, 245, 0.88);
        }

        .contractor-list {
            padding-left: 18px;
        }

        .contractor-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .contractor-secondary-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 999px;
            background: #eef4f1;
            color: #18362e;
            text-decoration: none;
            font-weight: 700;
        }
    </style>

    <div class="contractor-pending">
        <h1 class="auth-title">Crie seu tenant inicial</h1>
        <p class="auth-subtitle">Seu perfil web ja foi marcado como tenant admin. Falta criar o tenant para liberar o painel administrativo no primeiro acesso.</p>

        <section class="contractor-panel">
            <h2>O que acontece agora</h2>
            <p>Assim que voce criar o tenant, este usuario ja sera vinculado como admin e o sistema vai te levar direto para o dashboard administrativo.</p>
        </section>

        <form method="POST" action="{{ route('onboarding.contractor.store') }}" class="contractor-form">
            @csrf

            <div class="contractor-grid">
                <label class="contractor-field contractor-field-full" for="name">
                    <span class="contractor-label">Nome do tenant</span>
                    <input id="name" name="name" type="text" value="{{ $defaultTenantName }}" required class="field-control">
                </label>

                <label class="contractor-field" for="slug">
                    <span class="contractor-label">Slug publico</span>
                    <input id="slug" name="slug" type="text" value="{{ $defaultSlug }}" class="field-control" placeholder="meu-espaco">
                </label>

                <label class="contractor-field" for="contact_email">
                    <span class="contractor-label">E-mail de contato</span>
                    <input id="contact_email" name="contact_email" type="email" value="{{ $defaultContactEmail }}" class="field-control" placeholder="contato@empresa.com">
                </label>
            </div>

            <p class="contractor-helper">Se o slug ficar vazio, o sistema gera automaticamente a partir do nome. Se o slug escolhido ja existir, um sufixo numerico sera aplicado.</p>

            <button type="submit" class="auth-btn">Criar tenant e continuar</button>
        </form>

        <div class="contractor-actions">
            <a href="{{ route('tenants.select') }}" class="contractor-secondary-link">Ja tenho tenant vinculado</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="auth-btn">Sair</button>
            </form>
        </div>
    </div>
@endsection
