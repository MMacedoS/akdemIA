@extends('layouts.auth')

@section('title', 'Dois Fatores')

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Autenticacao em dois fatores</h1>
    <p class="mb-6 text-sm text-slate-600">Use o codigo do aplicativo autenticador.</p>

    <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="code" class="mb-1 block text-sm">Codigo</label>
            <input id="code" name="code" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2">
        </div>

        <div>
            <label for="recovery_code" class="mb-1 block text-sm">Codigo de recuperacao (opcional)</label>
            <input id="recovery_code" name="recovery_code" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2">
        </div>

        <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Validar</button>
    </form>
@endsection
