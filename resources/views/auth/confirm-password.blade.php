@extends('layouts.auth')

@section('title', 'Confirmar Senha')

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Confirmar senha</h1>
    <p class="mb-6 text-sm text-slate-600">Informe sua senha para continuar.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <div>
            <label for="password" class="mb-1 block text-sm">Senha</label>
            <input id="password" name="password" type="password" required class="w-full rounded-md border border-slate-300 px-3 py-2">
        </div>

        <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Confirmar</button>
    </form>
@endsection
