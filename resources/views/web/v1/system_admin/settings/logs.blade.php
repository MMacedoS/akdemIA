@extends('layouts.panel')

@section('pageTitle', 'System Admin - Logs Laravel')
@section('headerTitle', 'Logs Laravel')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="card">
            <h3>Leitura dos arquivos de log</h3>
            <p>Visualizacao somente leitura dos arquivos em storage/logs, com filtros simples para inspecao rapida.</p>

            <form method="GET" action="{{ route('system-admin.settings.logs.index') }}" class="form-grid" style="margin-top: 12px; align-items: end;">
                <div class="field">
                    <label for="file">Arquivo</label>
                    <select id="file" name="file">
                        @forelse (($availableLogs ?? collect()) as $availableLog)
                            <option value="{{ $availableLog['name'] }}" @selected(($selectedFile ?? 'laravel.log') === $availableLog['name'])>{{ $availableLog['name'] }}</option>
                        @empty
                            <option value="laravel.log">laravel.log</option>
                        @endforelse
                    </select>
                </div>

                <div class="field">
                    <label for="lines">Ultimas linhas</label>
                    <input id="lines" name="lines" type="number" min="50" max="2000" step="50" value="{{ $lineCount ?? 300 }}" inputmode="numeric">
                </div>

                <div class="field">
                    <label for="level">Nivel</label>
                    <select id="level" name="level">
                        <option value="">Todos</option>
                        @foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $logLevel)
                            <option value="{{ $logLevel }}" @selected(($level ?? '') === $logLevel)>{{ strtoupper($logLevel) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="date">Data</label>
                    <input id="date" name="date" type="date" value="{{ $date ?? '' }}">
                </div>

                <div class="field">
                    <label for="search">Buscar texto</label>
                    <input id="search" name="search" type="text" maxlength="255" value="{{ $search ?? '' }}" placeholder="Exception, SQLSTATE, usuario...">
                </div>

                <div class="field" style="display: flex; gap: 12px; align-items: center;">
                    <button type="submit" class="btn btn-primary">Atualizar leitura</button>
                </div>
            </form>

            <form method="POST" action="{{ route('system-admin.settings.logs.clear') }}" style="margin-top: 12px;" onsubmit="return confirm('Limpar o arquivo selecionado agora?');">
                @csrf
                <input type="hidden" name="file" value="{{ $selectedFile ?? 'laravel.log' }}">
                <input type="hidden" name="lines" value="{{ $lineCount ?? 300 }}">
                <input type="hidden" name="level" value="{{ $level ?? '' }}">
                <input type="hidden" name="date" value="{{ $date ?? '' }}">
                <input type="hidden" name="search" value="{{ $search ?? '' }}">
                <button type="submit" class="btn btn-soft">Limpar arquivo selecionado</button>
            </form>

            <div class="cards-grid" style="margin-top: 16px;">
                <div class="card">
                    <h3>Arquivo</h3>
                    <p>{{ $selectedFile ?? 'laravel.log' }}</p>
                </div>

                <div class="card">
                    <h3>Tamanho</h3>
                    <p>{{ ($fileSize ?? null) !== null ? number_format($fileSize / 1024, 2, ',', '.') . ' KB' : 'Nao disponivel' }}</p>
                </div>

                <div class="card">
                    <h3>Ultima atualizacao</h3>
                    <p>{{ ($lastModifiedAt ?? null)?->format('d/m/Y H:i:s') ?? 'Nao disponivel' }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Conteudo</h3>

            @if (! ($logExists ?? false))
                <p>O arquivo selecionado ainda nao existe.</p>
            @elseif (($logContent ?? null) === '')
                <p>Nenhuma linha encontrada com os filtros atuais.</p>
            @else
                <pre style="margin-top: 12px; max-height: 70vh; overflow: auto; padding: 16px; border-radius: 16px; background: #111827; color: #e5e7eb; font-size: 12px; line-height: 1.5; white-space: pre-wrap; word-break: break-word;">{{ $logContent ?? '' }}</pre>
            @endif
        </div>
    </div>
@endsection
