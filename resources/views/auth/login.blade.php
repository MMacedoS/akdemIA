@extends('layouts.auth')

@section('title', 'Login')

@section('content')
    <style>
        .social-auth-stack {
            display: grid;
            gap: 14px;
            margin-bottom: 18px;
        }

        .social-auth-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 48px;
            width: 100%;
            border-radius: 999px;
            border: 1px solid rgba(24, 54, 46, 0.14);
            background: linear-gradient(180deg, #ffffff, #f2f6f4);
            color: #18362e;
            text-decoration: none;
            font-weight: 700;
        }

        .social-auth-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #617068;
            font-size: 12px;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .social-auth-divider::before,
        .social-auth-divider::after {
            content: '';
            height: 1px;
            flex: 1;
            background: rgba(24, 54, 46, 0.12);
        }
    </style>

    <h1 class="auth-title">Entrar</h1>
    <p class="auth-subtitle">Acesse sua conta.</p>

    @if (!empty($canLoginWithGoogle))
        <div class="social-auth-stack">
            <a href="{{ route('auth.google.redirect') }}" class="social-auth-btn">
                <span>Google</span>
                <span>Continuar com Google</span>
            </a>
            <div class="social-auth-divider">ou entre com e-mail</div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label for="tenant_slug">Tenant</label>
            <select id="tenant_slug" name="tenant_slug" class="field-control">
                <option value="">Usar tenant padrao do trainee</option>
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->slug }}" {{ old('tenant_slug') === $tenant->slug ? 'selected' : '' }}>{{ $tenant->name }} ({{ $tenant->slug }})</option>
                @endforeach
            </select>
            <small>Para trainee, se nenhum tenant for escolhido, o sistema usa o tenant Plataforma ou o primeiro vinculado.</small>
        </div>

        <div class="form-group">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="field-control">
        </div>

        <div class="form-group">
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" required class="field-control">
        </div>

        <label class="remember-row">
            <input type="checkbox" name="remember">
            <span>Lembrar de mim</span>
        </label>

        <button type="submit" class="auth-btn">Entrar</button>
    </form>

    <div class="auth-links">
        @if (!empty($canResetPassword))
            <a href="{{ route('password.request') }}">Esqueci minha senha</a>
        @endif
    </div>
@endsection
