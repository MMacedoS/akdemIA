@extends('layouts.auth')

@section('title', 'Recuperar Senha')

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Recuperar senha</h1>
    <p class="mb-6 text-sm text-slate-600">Enviaremos um link de redefinicao para seu e-mail.</p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="w-full rounded-md border border-slate-300 px-3 py-2">
        </div>

        <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Enviar link</button>
    </form>
@endsection
