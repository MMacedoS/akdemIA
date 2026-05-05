@extends('layouts.auth')

@section('title', 'Cadastro')

@section('content')
    <h1 class="auth-title">Cadastro</h1>
    <p class="auth-subtitle">Crie sua conta.</p>

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

        <button type="submit" class="auth-btn">Cadastrar</button>
    </form>

    <div class="auth-links">
        <a href="{{ route('login') }}">Ja possui conta? Entrar</a>
    </div>
@endsection
