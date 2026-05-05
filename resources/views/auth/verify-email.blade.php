@extends('layouts.auth')

@section('title', 'Verificar E-mail')

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Verifique seu e-mail</h1>
    <p class="mb-6 text-sm text-slate-600">Antes de continuar, confirme seu endereco de e-mail.</p>

    <form method="POST" action="{{ route('verification.send') }}" class="space-y-4">
        @csrf
        <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Reenviar e-mail</button>
    </form>
@endsection
