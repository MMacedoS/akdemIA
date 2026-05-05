@extends('layouts.auth')

@section('title', 'Nova Senha')

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Redefinir senha</h1>
    <p class="mb-6 text-sm text-slate-600">Defina sua nova senha.</p>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="mb-1 block text-sm">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required class="w-full rounded-md border border-slate-300 px-3 py-2">
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm">Nova senha</label>
            <input id="password" name="password" type="password" required class="w-full rounded-md border border-slate-300 px-3 py-2">
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm">Confirmar senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-md border border-slate-300 px-3 py-2">
        </div>

        <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Salvar senha</button>
    </form>
@endsection
