@extends('layouts.auth')

@section('title', 'Selecionar Tenant')

@section('content')
    <h1 class="mb-2 text-xl font-semibold text-slate-900">Selecionar tenant</h1>
    <p class="mb-6 text-sm text-slate-600">Escolha o tenant para continuar no painel.</p>

    <form method="POST" action="{{ route('tenants.select.store') }}" class="space-y-4">
        @csrf

        <div>
            <label for="slug" class="mb-1 block text-sm font-medium text-slate-700">Tenant</label>
            <select id="slug" name="slug" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                <option value="">Selecione</option>
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->slug }}" {{ (string) old('slug', '') === (string) $tenant->slug || $selectedTenantId === (int) $tenant->id ? 'selected' : '' }}>
                        {{ $tenant->name }} ({{ $tenant->slug }})
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Continuar
        </button>
    </form>
@endsection
