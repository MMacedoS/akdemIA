@extends('layouts.auth')

@section('title', 'Login')

@section('content')
    <h1 class="auth-title">Entrar</h1>
    <p class="auth-subtitle">Acesse sua conta.</p>

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

        @if (!empty($canRegister))
            <a href="{{ route('register') }}">Criar conta</a>
        @endif
    </div>
@endsection
