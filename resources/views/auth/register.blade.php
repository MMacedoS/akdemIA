@extends('layouts.auth')

@section('title', 'Cadastro')

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

    <h1 class="auth-title">Cadastro</h1>
    <p class="auth-subtitle">Crie sua conta.</p>

    @if (!empty($canLoginWithGoogle))
        <div class="social-auth-stack">
            <a href="{{ route('auth.google.redirect') }}" class="social-auth-btn">
                <span>Google</span>
                <span>Criar conta com Google</span>
            </a>
            <div class="social-auth-divider">ou continue com cadastro manual</div>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="form-group">
            <label for="name">Nome</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required class="field-control">
        </div>

        <div class="form-group">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="field-control">
        </div>

        <div class="form-group">
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" required class="field-control">
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirmar senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required class="field-control">
        </div>

        <label class="remember-row" for="terms_of_use">
            <input id="terms_of_use" type="checkbox" name="terms_of_use" value="1" {{ old('terms_of_use') ? 'checked' : '' }} required>
            <span>Li e aceito os <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer">Termos de Uso</a>.</span>
        </label>

        <label class="remember-row" for="privacy_policy">
            <input id="privacy_policy" type="checkbox" name="privacy_policy" value="1" {{ old('privacy_policy') ? 'checked' : '' }} required>
            <span>Li e aceito a <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer">Politica de Privacidade</a>.</span>
        </label>

        <button type="submit" class="auth-btn">Cadastrar</button>
    </form>

    <div class="auth-links">
        <a href="{{ route('login') }}">Ja possui conta? Entrar</a>
    </div>
@endsection
