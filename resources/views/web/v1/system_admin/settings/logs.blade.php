@extends('layouts.panel')

@section('pageTitle', 'System Admin - Logs Laravel')
@section('headerTitle', 'Logs Laravel')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="card">
            <h3>Leitura do arquivo laravel.log</h3>
            <p>Visualizacao somente leitura do arquivo principal de logs da aplicacao.</p>

            <form method="GET" action="{{ route('system-admin.settings.logs.index') }}" class="form-grid" style="margin-top: 12px; align-items: end;">
                <div class="field">
                    <label for="lines">Ultimas linhas</label>
                    <input id="lines" name="lines" type="number" min="50" max="2000" step="50" value="{{ $lineCount }}" inputmode="numeric">
                </div>

                <div class="field" style="display: flex; gap: 12px; align-items: center;">
                    <button type="submit" class="btn btn-primary">Atualizar leitura</button>
                </div>
            </form>

            <form method="POST" action="{{ route('system-admin.settings.logs.clear') }}" style="margin-top: 12px;" onsubmit="return confirm('Limpar o arquivo laravel.log agora?');">
                @csrf
                <input type="hidden" name="lines" value="{{ $lineCount }}">
                <button type="submit" class="btn btn-soft">Limpar laravel.log</button>
            </form>

            <div class="cards-grid" style="margin-top: 16px;">
                <div class="card">
                    <h3>Arquivo</h3>
                    <p>{{ $logPath }}</p>
                </div>

                <div class="card">
                    <h3>Tamanho</h3>
                    <p>{{ $fileSize !== null ? number_format($fileSize / 1024, 2, ',', '.') . ' KB' : 'Nao disponivel' }}</p>
                </div>

                <div class="card">
                    <h3>Ultima atualizacao</h3>
                    <p>{{ $lastModifiedAt?->format('d/m/Y H:i:s') ?? 'Nao disponivel' }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Conteudo</h3>

            @if (! $logExists)
                <p>O arquivo storage/logs/laravel.log ainda nao existe.</p>
            @elseif ($logContent === '')
                <p>O arquivo esta vazio.</p>
            @else
                <pre style="margin-top: 12px; max-height: 70vh; overflow: auto; padding: 16px; border-radius: 16px; background: #111827; color: #e5e7eb; font-size: 12px; line-height: 1.5; white-space: pre-wrap; word-break: break-word;">{{ $logContent }}</pre>
            @endif
        </div>
    </div>
@endsection
