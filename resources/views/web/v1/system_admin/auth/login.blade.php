@extends('layouts.auth')

@section('title', 'Login Admin Sistema')

@section('content')
    <h1 class="auth-title">Admin do Sistema</h1>
    <p class="auth-subtitle">Acesso exclusivo sem tenant.</p>

    <form method="POST" action="{{ route('system-admin.login.store') }}">
        @csrf

        <div class="form-group">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="field-control">
        </div>

        <div class="form-group">
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" required class="field-control">
        </div>

        <label class="remember-row">
            <input type="checkbox" name="remember" value="1">
            <span>Lembrar de mim</span>
        </label>

        <button type="submit" class="auth-btn">Entrar como admin sistema</button>
    </form>

    <div class="auth-links">
        <a href="{{ route('login') }}">Login com tenant</a>
    </div>
@endsection
